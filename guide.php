<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php' ?>
  <style>
    @media (max-width: 768px) {
      #scrollTopBtn {
        display: none !important;
      }
    }

    .over {
      overflow: hidden !important;
    }

    .floating {
      animation: float 6s ease-in-out infinite;
    }

    .floating-slow {
      animation: float 8s ease-in-out infinite;
    }

    .floating-fast {
      animation: float 4s ease-in-out infinite;
    }

    @keyframes float {
      0% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(-20px);
      }

      100% {
        transform: translateY(0px);
      }
    }

    .pulse-gold {
      animation: pulse-gold 3s infinite;
    }

    @keyframes pulse-gold {
      0% {
        filter: drop-shadow(0 0 0.5rem rgba(245, 158, 11, 0));
      }

      50% {
        filter: drop-shadow(0 0 1rem rgba(245, 158, 11, 0.5));
      }

      100% {
        filter: drop-shadow(0 0 0.5rem rgba(245, 158, 11, 0));
      }
    }

    .rotate-slow {
      animation: rotate 15s linear infinite;
    }

    @keyframes rotate {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    .glow-path {
      filter: drop-shadow(0 0 3px rgba(245, 158, 11, 0.7));
    }

    .scale-in {
      animation: scaleIn 0.5s ease-out forwards;
      opacity: 0;
      transform: scale(0.8);
    }

    @keyframes scaleIn {
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .fade-in {
      animation: fadeIn 1s ease-out forwards;
      opacity: 0;
    }

    @keyframes fadeIn {
      to {
        opacity: 1;
      }
    }

    .slide-up {
      animation: slideUp 0.7s ease-out forwards;
      opacity: 0;
      transform: translateY(30px);
    }

    @keyframes slideUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .slide-in-right {
      animation: slideInRight 0.7s ease-out forwards;
      opacity: 0;
      transform: translateX(50px);
    }

    @keyframes slideInRight {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .slide-in-left {
      animation: slideInLeft 0.7s ease-out forwards;
      opacity: 0;
      transform: translateX(-50px);
    }

    @keyframes slideInLeft {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .shimmer {
      background: linear-gradient(to right,
          rgba(245, 158, 11, 0) 0%,
          rgba(245, 158, 11, 0.2) 20%,
          rgba(245, 158, 11, 0) 40%);
      background-size: 200% 100%;
      animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
      0% {
        background-position: 100% 0;
      }

      100% {
        background-position: -100% 0;
      }
    }

    .number-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 18px;
    }

    /* Tree visualization */
    .tree-connector {
      position: absolute;
      width: 2px;
      background: linear-gradient(to bottom, #f59e0b, rgba(245, 158, 11, 0.1));
    }

    .tree-connector-horizontal {
      position: absolute;
      height: 2px;
      background: linear-gradient(to right, #f59e0b, rgba(245, 158, 11, 0.1));
    }

    /* Token System */
    .token-glow {
      box-shadow: 0 0 15px rgba(245, 158, 11, 0.5);
    }

    /* Progress bars */
    .progress-bar {
      height: 8px;
      border-radius: 4px;
      background: linear-gradient(to right, #f59e0b, #d97706);
      transition: width 1s ease-in-out;
    }

    /* Leader board */
    .leader-item:hover {
      background: linear-gradient(90deg, rgba(245, 158, 11, 0.1), transparent);
      border-left: 3px solid #f59e0b;
    }

    /* Support chat bubble */
    .chat-bubble {
      position: relative;
      background: #1f2937;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1rem;
      border-left: 3px solid #f59e0b;
    }

    .chat-bubble:after {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      width: 0;
      height: 0;
      border: 8px solid transparent;
      border-right-color: #1f2937;
      border-left: 0;
      margin-top: -8px;
      margin-left: -8px;
    }
  </style>
</head>

<body class="bg-black text-white font-sans">
  <?php include 'includes/navbar.php' ?>

  <!-- Hero Section -->
  <div class="over relative py-16 md:py-24 overflow-hidden">
    <div class="absolute inset-0 z-0">
      <div class="absolute inset-0 bg-gradient-to-b from-black to-gray-900"></div>
      <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-gray-700/20 via-gray-900/40 to-black"></div>

      <!-- Background SVG Elements -->
      <svg class="absolute w-full h-full opacity-20" viewBox="0 0 1440 800" preserveAspectRatio="none">
        <path d="M-100,200 C300,100 400,500 600,300 S1000,400 1200,200 S1500,500 1600,400" stroke="#f59e0b" stroke-width="0.5" fill="none" />
        <path d="M-100,500 C200,600 500,300 800,500 S1100,200 1400,500 S1700,300 1900,500" stroke="#f59e0b" stroke-width="0.5" fill="none" />
        <path d="M100,700 C300,500 700,700 900,500 S1300,600 1500,400" stroke="#f59e0b" stroke-width="0.5" fill="none" />
      </svg>

      <!-- Decorative dots -->
      <svg class="absolute w-full h-full opacity-30" viewBox="0 0 1440 800" preserveAspectRatio="none">
        <circle cx="100" cy="100" r="2" fill="#f59e0b" />
        <circle cx="200" cy="150" r="1" fill="#f59e0b" />
        <circle cx="300" cy="200" r="1.5" fill="#f59e0b" />
        <circle cx="1100" cy="150" r="2" fill="#f59e0b" />
        <circle cx="1200" cy="250" r="1" fill="#f59e0b" />
        <circle cx="1300" cy="100" r="1.5" fill="#f59e0b" />
        <circle cx="600" cy="700" r="2" fill="#f59e0b" />
        <circle cx="700" cy="600" r="1" fill="#f59e0b" />
        <circle cx="800" cy="650" r="1.5" fill="#f59e0b" />
      </svg>
    </div>

    <!-- Decorative SVG Elements -->
    <div class="absolute right-1/4 top-20 opacity-70 w-20 h-20 floating-slow hidden md:block">
      <svg viewBox="0 0 100 100" class="pulse-gold">
        <circle cx="50" cy="50" r="45" fill="#111" stroke="#f59e0b" stroke-width="2" />
        <path d="M30,50 L70,50 M50,30 L50,70" stroke="#f59e0b" stroke-width="2" />
        <circle cx="50" cy="50" r="15" fill="#f59e0b" opacity="0.3" />
      </svg>
    </div>

    <div class="absolute left-1/4 bottom-20 opacity-70 w-24 h-24 floating hidden md:block">
      <svg viewBox="0 0 100 100" class="pulse-gold">
        <circle cx="50" cy="50" r="45" fill="none" stroke="#f59e0b" stroke-width="2" />
        <path d="M30,30 L70,70 M30,70 L70,30" stroke="#f59e0b" stroke-width="2" />
        <circle cx="50" cy="50" r="5" fill="#f59e0b" />
      </svg>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 relative z-10">
      <div class="max-w-3xl mx-auto text-center scale-in" style="animation-delay: 0.2s;">
        <!-- Decorative circles above heading -->
        <div class="flex justify-center mb-6">
          <svg width="200" height="40" class="rotate-slow opacity-70">
            <circle cx="100" cy="20" r="15" fill="none" stroke="#f59e0b" stroke-width="1" />
            <circle cx="100" cy="20" r="10" fill="none" stroke="#f59e0b" stroke-width="1" />
            <circle cx="100" cy="20" r="5" fill="#f59e0b" />
            <path d="M130,20 L170,20" stroke="#f59e0b" stroke-width="1" />
            <path d="M30,20 L70,20" stroke="#f59e0b" stroke-width="1" />
          </svg>
        </div>

        <h1 class="text-4xl md:text-5xl font-extrabold mb-6">
          <span class="block">How It</span>
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">Works</span>
        </h1>

        <p class="text-lg text-gray-300 mb-8 max-w-xl mx-auto fade-in" style="animation-delay: 0.4s;">
          Understand our complete investment ecosystem and maximize your earnings
        </p>

        <!-- SVG graph under paragraph -->
        <div class="mb-8 flex justify-center">
          <svg width="300" height="60" viewBox="0 0 300 60">
            <rect x="0" y="0" width="300" height="60" fill="transparent" />
            <!-- Simple trend line -->
            <path d="M10,50 C50,40 70,45 100,25 S150,10 200,15 S250,25 290,10" stroke="#f59e0b" stroke-width="2" fill="none" class="glow-path" />
            <!-- Data points -->
            <circle cx="10" cy="50" r="3" fill="#f59e0b" />
            <circle cx="70" cy="45" r="3" fill="#f59e0b" />
            <circle cx="100" cy="25" r="3" fill="#f59e0b" />
            <circle cx="150" cy="10" r="3" fill="#f59e0b" />
            <circle cx="200" cy="15" r="3" fill="#f59e0b" />
            <circle cx="250" cy="25" r="3" fill="#f59e0b" />
            <circle cx="290" cy="10" r="3" fill="#f59e0b" />
            <!-- 20% marker -->
            <rect x="240" y="5" width="45" height="20" fill="rgba(0,0,0,0.7)" rx="10" />
            <text x="262" y="19" text-anchor="middle" fill="#f59e0b" font-size="10" font-weight="bold">+20%</text>
          </svg>
        </div>

        <div class="inline-flex rounded-md shadow">
          <a href="#investment-plans" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-black bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 md:py-4 md:text-lg md:px-10 transition duration-300 transform hover:scale-105 shadow-xl">
            <svg width="24" height="24" viewBox="0 0 24 24" class="mr-2" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 8V16M8 12H16M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Get Started
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Navigation -->
  <div class="over py-8 bg-gray-900">
    <div class="container mx-auto px-6">
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 max-w-5xl mx-auto">
        <a href="#investment-plans" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition duration-300 transform hover:scale-105">
          <div class="flex justify-center mb-2">
            <i class="fas fa-chart-line text-yellow-500 text-2xl"></i>
          </div>
          <span class="text-sm font-medium">Investment Plans</span>
        </a>

        <a href="#referral-system" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition duration-300 transform hover:scale-105">
          <div class="flex justify-center mb-2">
            <i class="fas fa-users text-yellow-500 text-2xl"></i>
          </div>
          <span class="text-sm font-medium">Referral System</span>
        </a>

        <a href="#token-system" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition duration-300 transform hover:scale-105">
          <div class="flex justify-center mb-2">
            <i class="fas fa-coins text-yellow-500 text-2xl"></i>
          </div>
          <span class="text-sm font-medium">Token System</span>
        </a>

        <a href="#leaderboard" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition duration-300 transform hover:scale-105">
          <div class="flex justify-center mb-2">
            <i class="fas fa-trophy text-yellow-500 text-2xl"></i>
          </div>
          <span class="text-sm font-medium">Leaderboard</span>
        </a>

        <a href="#support" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition duration-300 transform hover:scale-105">
          <div class="flex justify-center mb-2">
            <i class="fas fa-headset text-yellow-500 text-2xl"></i>
          </div>
          <span class="text-sm font-medium">Support</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Investment Process Overview -->
  <div class="over py-16 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-4 sm:px-6">
      <div class="text-center mb-12">
        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4">Simple 4-Step Investment Process</h2>
        <p class="text-gray-400 max-w-2xl mx-auto text-sm sm:text-base">
          Our streamlined process makes investing effortless and profitable
        </p>
      </div>

      <div class="max-w-5xl mx-auto">
        <div class="relative">
          <!-- Process step connection line -->
          <div class="absolute left-[50%] top-0 bottom-0 w-0.5 bg-gray-700 hidden md:block"></div>

          <!-- Step 1 -->
          <div class="flex flex-col md:flex-row items-center mb-16 relative">
            <div class="w-full md:w-1/2 mb-6 md:mb-0 md:pr-12 text-center md:text-right slide-in-left" style="animation-delay: 0.3s;">
              <h3 class="text-xl sm:text-2xl font-bold text-yellow-500 mb-3">Choose a Plan</h3>
              <p class="text-gray-300 text-sm sm:text-base">
                Select from our range of investment plans based on your budget and financial goals. Each plan offers 20% returns with different minimum investment requirements.
              </p>
            </div>
            <div class="md:absolute static left-[50%] transform md:-translate-x-1/2 z-10 bg-black rounded-full p-4 sm:p-6 border-4 border-yellow-500 scale-in mb-6 md:mb-0" style="animation-delay: 0.5s;">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-12 sm:w-12 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="w-full md:w-1/2 md:pl-12 slide-in-right" style="animation-delay: 0.3s;">
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-xs sm:text-sm text-gray-400">Basic</span>
                  <span class="text-xs sm:text-sm font-bold text-yellow-500">3,000 PKR</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                  <span class="text-xs sm:text-sm text-gray-400">Standard</span>
                  <span class="text-xs sm:text-sm font-bold text-yellow-500">5,000 PKR</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                  <span class="text-xs sm:text-sm text-gray-400">Premium</span>
                  <span class="text-xs sm:text-sm font-bold text-yellow-500">10,000 PKR</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs sm:text-sm text-gray-400">Professional</span>
                  <span class="text-xs sm:text-sm font-bold text-yellow-500">20,000 PKR</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="flex flex-col md:flex-row-reverse items-center mb-16 relative">
            <div class="w-full md:w-1/2 mb-6 md:mb-0 md:pl-12 text-center md:text-left slide-in-right" style="animation-delay: 0.5s;">
              <h3 class="text-xl sm:text-2xl font-bold text-yellow-500 mb-3">Make Your Deposit</h3>
              <p class="text-gray-300 text-sm sm:text-base">
                Fund your investment account using our secure payment methods. Your deposit is processed instantly and added to your account balance.
              </p>
            </div>
            <div class="md:absolute static left-[50%] transform md:-translate-x-1/2 z-10 bg-black rounded-full p-4 sm:p-6 border-4 border-yellow-500 scale-in mb-6 md:mb-0" style="animation-delay: 0.7s;">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-12 sm:w-12 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="w-full md:w-1/2 md:pr-12 slide-in-left" style="animation-delay: 0.5s;">
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700">
                <div class="flex items-center mb-3">
                  <i class="fas fa-university text-yellow-500 mr-2 text-sm sm:text-base"></i>
                  <span class="text-gray-300 text-xs sm:text-sm">Bank Transfer</span>
                </div>
                <div class="flex items-center mb-3">
                  <i class="fab fa-bitcoin text-yellow-500 mr-2 text-sm sm:text-base"></i>
                  <span class="text-gray-300 text-xs sm:text-sm">Cryptocurrency</span>
                </div>
                <div class="flex items-center">
                  <i class="fas fa-credit-card text-yellow-500 mr-2 text-sm sm:text-base"></i>
                  <span class="text-gray-300 text-xs sm:text-sm">Credit/Debit Cards</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="flex flex-col md:flex-row items-center mb-16 relative">
            <div class="w-full md:w-1/2 mb-6 md:mb-0 md:pr-12 text-center md:text-right slide-in-left" style="animation-delay: 0.7s;">
              <h3 class="text-xl sm:text-2xl font-bold text-yellow-500 mb-3">Earn Profits</h3>
              <p class="text-gray-300 text-sm sm:text-base">
                Our algorithms work 24/7 to generate consistent returns. Watch your investment grow at a rate of 20% with profits credited to your account after each 24-hour cycle.
              </p>
            </div>
            <div class="md:absolute static left-[50%] transform md:-translate-x-1/2 z-10 bg-black rounded-full p-4 sm:p-6 border-4 border-yellow-500 scale-in mb-6 md:mb-0" style="animation-delay: 0.9s;">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-12 sm:w-12 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="w-full md:w-1/2 md:pl-12 slide-in-right" style="animation-delay: 0.7s;">
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700">
                <div class="text-center mb-2">
                  <span class="text-2xl sm:text-3xl font-bold text-yellow-500">20%</span>
                  <span class="text-gray-400 ml-1 text-xs sm:text-sm">Return</span>
                </div>
                <div class="mb-2">
                  <div class="flex justify-between text-xs sm:text-sm mb-1">
                    <span class="text-gray-400">Progress</span>
                    <span class="text-gray-300">16/24 Hours</span>
                  </div>
                  <div class="w-full bg-gray-700 rounded-full h-2">
                    <div class="progress-bar" style="width: 67%;"></div>
                  </div>
                </div>
                <div class="text-center text-xs sm:text-sm text-gray-400">
                  Next profit in: <span class="text-white">08:24:12</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 4 -->
          <div class="flex flex-col md:flex-row-reverse items-center relative">
            <div class="w-full md:w-1/2 mb-6 md:mb-0 md:pl-12 text-center md:text-left slide-in-right" style="animation-delay: 0.9s;">
              <h3 class="text-xl sm:text-2xl font-bold text-yellow-500 mb-3">Withdraw Your Earnings</h3>
              <p class="text-gray-300 text-sm sm:text-base">
                Request a withdrawal anytime and receive your funds through your preferred payment method. Standard withdrawals are processed within 24 hours, with expedited options for premium members.
              </p>
            </div>
            <div class="md:absolute static left-[50%] transform md:-translate-x-1/2 z-10 bg-black rounded-full p-4 sm:p-6 border-4 border-yellow-500 scale-in mb-6 md:mb-0" style="animation-delay: 1.1s;">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-12 sm:w-12 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="w-full md:w-1/2 md:pr-12 slide-in-left" style="animation-delay: 0.9s;">
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700">
                <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-700">
                  <span class="text-gray-300 text-xs sm:text-sm">Available Balance</span>
                  <span class="text-lg sm:text-xl font-bold text-yellow-500">₹ 12,500</span>
                </div>
                <div class="flex justify-between items-center mb-3">
                  <span class="text-gray-300 text-xs sm:text-sm">Processing Time</span>
                  <span class="text-white text-xs sm:text-sm">24 Hours</span>
                </div>
                <div class="mt-4">
                  <button class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-2 px-4 rounded transition duration-300 text-xs sm:text-sm">
                    Request Withdrawal
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Investment Plans Section -->
  <div id="investment-plans" class="over py-16 bg-black">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-white mb-4">Investment Plans</h2>
        <p class="text-gray-400 max-w-2xl mx-auto">
          Choose the investment plan that aligns with your financial goals - all offering a consistent 20% return
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
        <!-- Basic Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group slide-up" style="animation-delay: 0.3s;">
          <div class="p-6">
            <h3 class="text-xl font-bold text-white mb-3">Basic</h3>
            <div class="flex items-baseline mb-4">
              <span class="text-3xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-4">Perfect for beginners looking to start their investment journey.</p>

            <ul class="space-y-3 mb-6">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">3,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Profit after 24 Hours</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Standard customer support</span>
              </li>
            </ul>

            <button class="w-full bg-gray-700 group-hover:bg-gradient-to-r group-hover:from-yellow-500 group-hover:to-yellow-600 text-white group-hover:text-black font-bold py-3 px-4 rounded-lg transition duration-300">
              Get Started
            </button>
          </div>
        </div>

        <!-- Standard Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group slide-up" style="animation-delay: 0.5s;">
          <div class="p-6">
            <h3 class="text-xl font-bold text-white mb-3">Standard</h3>
            <div class="flex items-baseline mb-4">
              <span class="text-3xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-4">Balanced plan for serious investors looking for consistent returns.</p>

            <ul class="space-y-3 mb-6">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">5,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Profit after 24 Hours</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Enhanced customer support</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Email notifications</span>
              </li>
            </ul>

            <button class="w-full bg-gray-700 group-hover:bg-gradient-to-r group-hover:from-yellow-500 group-hover:to-yellow-600 text-white group-hover:text-black font-bold py-3 px-4 rounded-lg transition duration-300">
              Get Started
            </button>
          </div>
        </div>

        <!-- Premium Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl overflow-hidden shadow-2xl border border-yellow-500 transform scale-105 relative slide-up" style="animation-delay: 0.7s;">
          <div class="absolute top-0 right-0 bg-yellow-500 text-black font-bold px-4 py-1">
            Most Popular
          </div>
          <div class="p-6">
            <h3 class="text-xl font-bold text-white mb-3">Premium</h3>
            <div class="flex items-baseline mb-4">
              <span class="text-3xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-4">Optimized returns for serious investors with exclusive benefits.</p>

            <ul class="space-y-3 mb-6">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">10,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Profit after 24 Hours</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Priority support</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Early access to new features</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Expedited withdrawals</span>
              </li>
            </ul>

            <button class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
              Get Started
            </button>
          </div>
        </div>
      </div>

      <!-- Professional and Custom Plan -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto mt-8">
        <!-- Professional Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group slide-up" style="animation-delay: 0.9s;">
          <div class="p-6">
            <h3 class="text-xl font-bold text-white mb-3">Professional</h3>
            <div class="flex items-baseline mb-4">
              <span class="text-3xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-4">Exclusive access to our highest yield investment strategy.</p>

            <ul class="space-y-3 mb-6">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">20,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Profit after 24 Hours</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">24/7 VIP dedicated support</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Custom profit strategies</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Personalized investment advisor</span>
              </li>
            </ul>

            <button class="w-full bg-gray-700 group-hover:bg-gradient-to-r group-hover:from-yellow-500 group-hover:to-yellow-600 text-white group-hover:text-black font-bold py-3 px-4 rounded-lg transition duration-300">
              Get Started
            </button>
          </div>
        </div>

        <!-- Custom Plan -->
        <div id="custom-plan" class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-xl shadow-lg overflow-hidden border border-yellow-700 hover:border-yellow-500 transition-all duration-300 premium-card shimmer slide-up" style="animation-delay: 1.1s;">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Custom Plan</h3>
              <span class="bg-yellow-800 rounded-full px-3 py-1 text-sm font-semibold flex items-center">
                <i class="fas fa-crown text-yellow-400 mr-1 text-xs"></i>
                Exclusive
              </span>
            </div>

            <div class="mb-4 flex items-baseline bg-black bg-opacity-20 p-3 rounded-lg border-l-2 border-yellow-600">
              <span class="text-3xl font-bold">20%</span>
              <span class="text-lg text-yellow-400 ml-2">return</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <ul class="space-y-2 mb-6">
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">25,000 PKR minimum investment</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">No maximum limit</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">Profit after 24 hours</span>
                  </li>
                </ul>
              </div>
              <div>
                <ul class="space-y-2 mb-6">
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">Premium 24/7 support</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">Personalized strategy</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">Highest referral commissions</span>
                  </li>
                </ul>
              </div>
            </div>

            <button class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
              Create Custom Plan
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Referral System -->
  <div id="referral-system" class="over py-12 md:py-16 bg-gradient-to-b from-gray-900 to-black">
    <div class="container mx-auto px-4 sm:px-6">
      <div class="text-center mb-10 md:mb-12">
        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3 md:mb-4">Multi-Level Referral System</h2>
        <p class="text-sm sm:text-base text-gray-400 max-w-2xl mx-auto">
          Amplify your earnings by inviting others to join our platform
        </p>
      </div>

      <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center mb-10 md:mb-16">
          <div class="slide-up" style="animation-delay: 0.3s;">
            <h3 class="text-xl sm:text-2xl font-bold text-yellow-500 mb-4 md:mb-6 text-center md:text-left">Earn From Your Network</h3>
            <p class="text-sm sm:text-base text-gray-300 mb-6 text-center md:text-left">
              Our powerful multi-level referral system allows you to earn commissions from your direct referrals and their subsequent referrals, creating a sustainable passive income stream.
            </p>

            <div class="space-y-3 md:space-y-4">
              <div class="flex items-center">
                <div class="number-circle w-8 h-8 text-sm flex-shrink-0 mr-3 md:mr-4">1</div>
                <div class="text-xs sm:text-sm md:text-base text-gray-300">Get your unique referral link from your dashboard</div>
              </div>

              <div class="flex items-center">
                <div class="number-circle w-8 h-8 text-sm flex-shrink-0 mr-3 md:mr-4">2</div>
                <div class="text-xs sm:text-sm md:text-base text-gray-300">Share with friends, family, or on social media</div>
              </div>

              <div class="flex items-center">
                <div class="number-circle w-8 h-8 text-sm flex-shrink-0 mr-3 md:mr-4">3</div>
                <div class="text-xs sm:text-sm md:text-base text-gray-300">Earn commissions when they invest</div>
              </div>

              <div class="flex items-center">
                <div class="number-circle w-8 h-8 text-sm flex-shrink-0 mr-3 md:mr-4">4</div>
                <div class="text-xs sm:text-sm md:text-base text-gray-300">Build your network and increase your earnings</div>
              </div>
            </div>
          </div>

          <div class="relative scale-in" style="animation-delay: 0.5s;">
            <!-- Referral Tree Visualization -->
            <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-4 sm:p-6 md:p-8 rounded-xl shadow-xl border border-gray-700 relative overflow-x-auto">
              <h3 class="text-lg sm:text-xl font-bold mb-4 md:mb-6 text-center">Your Referral Network</h3>

              <!-- Tree visualization -->
              <div class="relative h-64 sm:h-72 md:h-80 min-w-[300px]">
                <!-- You (Level 0) -->
                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 bg-yellow-500 text-black p-2 sm:p-3 rounded-lg font-bold shadow-lg">
                  <div class="flex items-center">
                    <i class="fas fa-user mr-1 sm:mr-2 text-xs sm:text-sm"></i>
                    <span class="text-xs sm:text-sm">You</span>
                  </div>
                </div>

                <!-- Vertical connector -->
                <div class="tree-connector absolute h-8 sm:h-10 md:h-12 left-1/2 transform -translate-x-1/2 top-8 sm:top-10 md:top-12"></div>

                <!-- Level 1 (Direct Referrals) -->
                <div class="absolute top-16 sm:top-20 md:top-24 w-full flex justify-around">
                  <!-- Left referral -->
                  <div class="bg-gray-700 p-1 sm:p-2 rounded-lg border border-gray-600 text-center shadow-lg max-w-[95px] sm:max-w-none">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-user mr-1 text-green-400 text-xs"></i>
                      <span class="text-xs whitespace-nowrap">Direct Referral</span>
                    </div>
                    <div class="text-[10px] sm:text-xs mt-1 text-yellow-500">7% Commission</div>
                  </div>

                  <!-- Mid referral - Hide on smallest screens -->
                  <div class="hidden sm:block bg-gray-700 p-1 sm:p-2 rounded-lg border border-gray-600 text-center shadow-lg">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-user mr-1 text-green-400 text-xs"></i>
                      <span class="text-xs">Direct Referral</span>
                    </div>
                    <div class="text-[10px] sm:text-xs mt-1 text-yellow-500">7% Commission</div>
                  </div>

                  <!-- Right referral -->
                  <div class="bg-gray-700 p-1 sm:p-2 rounded-lg border border-gray-600 text-center shadow-lg max-w-[95px] sm:max-w-none">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-user mr-1 text-green-400 text-xs"></i>
                      <span class="text-xs whitespace-nowrap">Direct Referral</span>
                    </div>
                    <div class="text-[10px] sm:text-xs mt-1 text-yellow-500">7% Commission</div>
                  </div>
                </div>

                <!-- Vertical connectors to level 2 - Adjust for mobile -->
                <div class="tree-connector absolute h-10 sm:h-12 left-[25%] sm:left-[16.7%] top-28 sm:top-32 md:top-36"></div>
                <div class="hidden sm:block tree-connector absolute h-10 sm:h-12 left-[50%] transform -translate-x-1/2 top-32 md:top-36"></div>
                <div class="tree-connector absolute h-10 sm:h-12 left-[75%] sm:left-[83.3%] top-28 sm:top-32 md:top-36"></div>

                <!-- Level 2 (Indirect Referrals) - Simplified for mobile -->
                <div class="absolute top-38 sm:top-44 md:top-48 w-full grid grid-cols-2 sm:grid-cols-6 gap-1">
                  <div class="bg-gray-700 p-1 rounded-lg border border-gray-600 text-center text-[10px] sm:text-xs shadow-lg col-span-1 sm:col-span-2">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-user mr-1 text-blue-400 text-[10px]"></i>
                      <span>Level 2</span>
                    </div>
                    <div class="text-[10px] text-yellow-500">3%</div>
                  </div>

                  <!-- Middle level 2 - Hidden on smallest screens -->
                  <div class="hidden sm:block bg-gray-700 p-1 rounded-lg border border-gray-600 text-center text-[10px] sm:text-xs shadow-lg col-span-2">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-user mr-1 text-blue-400 text-[10px]"></i>
                      <span>Level 2</span>
                    </div>
                    <div class="text-[10px] text-yellow-500">3%</div>
                  </div>

                  <div class="bg-gray-700 p-1 rounded-lg border border-gray-600 text-center text-[10px] sm:text-xs shadow-lg col-span-1 sm:col-span-2">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-user mr-1 text-blue-400 text-[10px]"></i>
                      <span>Level 2</span>
                    </div>
                    <div class="text-[10px] text-yellow-500">3%</div>
                  </div>
                </div>

                <!-- Level 3 (Indirect Referrals) -->
                <div class="absolute top-56 sm:top-60 md:top-64 w-full text-center">
                  <div class="bg-gray-800 py-1 sm:py-2 px-2 sm:px-4 rounded-lg border border-gray-700 inline-block shadow-lg">
                    <div class="flex items-center justify-center">
                      <i class="fas fa-users mr-1 sm:mr-2 text-purple-400 text-[10px] sm:text-xs"></i>
                      <span class="text-[10px] sm:text-xs whitespace-nowrap">Level 3 Referrals (1% Commission)</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Commission Info -->
              <div class="mt-4 bg-gray-800 bg-opacity-50 p-2 sm:p-3 rounded-lg text-center">
                <p class="text-white text-xs sm:text-sm">Earn up to <span class="text-yellow-500 font-bold">11%</span> across 3 referral levels</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Commission Rates Table -->
        <div class="bg-gray-800 bg-opacity-50 rounded-xl overflow-hidden shadow-xl border border-gray-700 slide-up" style="animation-delay: 0.7s;">
          <div class="p-4 sm:p-6">
            <h3 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6 text-center">Referral Commission Rates</h3>

            <div class="overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
              <table class="w-full min-w-[480px]">
                <thead>
                  <tr class="border-b border-gray-700">
                    <th class="py-2 sm:py-3 px-2 sm:px-4 text-left text-xs sm:text-sm">Referral Level</th>
                    <th class="py-2 sm:py-3 px-2 sm:px-4 text-center text-xs sm:text-sm">Commission Rate</th>
                    <th class="py-2 sm:py-3 px-2 sm:px-4 text-center text-xs sm:text-sm">Example (10,000 PKR)</th>
                    <th class="py-2 sm:py-3 px-2 sm:px-4 text-right text-xs sm:text-sm">Paid</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="border-b border-gray-700">
                    <td class="py-2 sm:py-3 px-2 sm:px-4">
                      <div class="flex items-center">
                        <div class="number-circle w-5 h-5 sm:w-6 sm:h-6 text-[10px] sm:text-xs mr-2 sm:mr-3 flex-shrink-0">1</div>
                        <span class="text-xs sm:text-sm whitespace-nowrap">Direct Referral (Level 1)</span>
                      </div>
                    </td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-center text-yellow-500 font-bold text-xs sm:text-sm">7%</td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-center text-xs sm:text-sm">700 PKR</td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-right text-green-500 text-xs sm:text-sm">Immediately</td>
                  </tr>
                  <tr class="border-b border-gray-700">
                    <td class="py-2 sm:py-3 px-2 sm:px-4">
                      <div class="flex items-center">
                        <div class="number-circle w-5 h-5 sm:w-6 sm:h-6 text-[10px] sm:text-xs mr-2 sm:mr-3 flex-shrink-0">2</div>
                        <span class="text-xs sm:text-sm">Level 2 Referral</span>
                      </div>
                    </td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-center text-yellow-500 font-bold text-xs sm:text-sm">3%</td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-center text-xs sm:text-sm">300 PKR</td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-right text-green-500 text-xs sm:text-sm">Immediately</td>
                  </tr>
                  <tr>
                    <td class="py-2 sm:py-3 px-2 sm:px-4">
                      <div class="flex items-center">
                        <div class="number-circle w-5 h-5 sm:w-6 sm:h-6 text-[10px] sm:text-xs mr-2 sm:mr-3 flex-shrink-0">3</div>
                        <span class="text-xs sm:text-sm">Level 3 Referral</span>
                      </div>
                    </td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-center text-yellow-500 font-bold text-xs sm:text-sm">1%</td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-center text-xs sm:text-sm">100 PKR</td>
                    <td class="py-2 sm:py-3 px-2 sm:px-4 text-right text-green-500 text-xs sm:text-sm">Immediately</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 sm:mt-6 bg-gray-900 p-3 sm:p-4 rounded-lg border border-gray-700">
              <div class="flex items-start">
                <i class="fas fa-info-circle text-yellow-500 mt-1 mr-2 sm:mr-3 text-xs sm:text-base flex-shrink-0"></i>
                <p class="text-gray-300 text-xs sm:text-sm">
                  Referral commissions are paid instantly when your referrals make an investment. There's no limit to how many people you can refer or how much you can earn. Commissions are automatically credited to your account balance and can be withdrawn anytime.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Token System -->
  <div id="token-system" class="over py-16 bg-black">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-white mb-4">Token System & Staking</h2>
        <p class="text-gray-400 max-w-2xl mx-auto">
          Earn additional rewards through our innovative token system
        </p>
      </div>

      <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center mb-16">
          <div class="relative scale-in" style="animation-delay: 0.3s;">
            <!-- Token Visualization -->
            <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-8 rounded-xl shadow-xl border border-gray-700 relative">
              <div class="flex justify-center mb-6">
                <div class="relative">
                  <svg width="160" height="160" viewBox="0 0 100 100" class="pulse-gold">
                    <defs>
                      <linearGradient id="tokenGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#f7e084" />
                        <stop offset="50%" stop-color="#f59e0b" />
                        <stop offset="100%" stop-color="#e67e00" />
                      </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="45" fill="#111" stroke="url(#tokenGradient)" stroke-width="2" />
                    <circle cx="50" cy="50" r="40" fill="url(#tokenGradient)" opacity="0.2" />
                    <text x="50" y="40" text-anchor="middle" fill="#f59e0b" font-size="12" font-weight="bold">APX</text>
                    <text x="50" y="60" text-anchor="middle" fill="#f59e0b" font-size="16" font-weight="bold">TOKEN</text>
                  </svg>
                  <div class="absolute top-0 left-0 right-0 bottom-0 token-glow rounded-full"></div>
                </div>
              </div>

              <div class="text-center mb-6">
                <p class="text-xl font-bold text-yellow-500">AutoProftX Token</p>
                <p class="text-gray-400">Earn & Stake for Additional Returns</p>
              </div>

              <div class="space-y-4">
                <div class="bg-gray-800 bg-opacity-50 p-3 rounded-lg border-l-2 border-yellow-500">
                  <div class="flex justify-between">
                    <span class="text-gray-300">Current Price:</span>
                    <span class="text-white font-bold">₹ 10.25</span>
                  </div>
                </div>

                <div class="bg-gray-800 bg-opacity-50 p-3 rounded-lg border-l-2 border-yellow-500">
                  <div class="flex justify-between">
                    <span class="text-gray-300">Market Cap:</span>
                    <span class="text-white font-bold">₹ 5,125,000</span>
                  </div>
                </div>

                <div class="bg-gray-800 bg-opacity-50 p-3 rounded-lg border-l-2 border-yellow-500">
                  <div class="flex justify-between">
                    <span class="text-gray-300">24h Change:</span>
                    <span class="text-green-500 font-bold">+4.2%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="slide-up" style="animation-delay: 0.5s;">
            <h3 class="text-2xl font-bold text-yellow-500 mb-6">Boost Your Earnings with APX Tokens</h3>
            <p class="text-gray-300 mb-6">
              APX Tokens are our platform's native digital asset that allows you to participate in governance, stake for additional returns, and access exclusive premium features.
            </p>

            <div class="space-y-6">
              <div class="bg-gray-800 p-3 rounded-lg flex items-start mb-4">
                <i class="fas fa-coins text-yellow-500 mt-1 mr-3"></i>
                <div>
                  <h4 class="font-bold text-white mb-1">Earn Tokens</h4>
                  <p class="text-gray-300 text-sm">
                    Receive APX tokens for every investment you make, as well as through referrals and special promotions.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-3 rounded-lg flex items-start mb-4">
                <i class="fas fa-chart-line text-yellow-500 mt-1 mr-3"></i>
                <div>
                  <h4 class="font-bold text-white mb-1">Stake for Returns</h4>
                  <p class="text-gray-300 text-sm">
                    Lock your tokens in our staking pool to earn up to 15% additional annual returns on top of your regular investments.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-3 rounded-lg flex items-start">
                <i class="fas fa-unlock-alt text-yellow-500 mt-1 mr-3"></i>
                <div>
                  <h4 class="font-bold text-white mb-1">Unlock Premium Features</h4>
                  <p class="text-gray-300 text-sm">
                    Use tokens to access premium features, higher referral rates, and exclusive investment opportunities.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Staking Info -->
        <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700slide-up" style="animation-delay: 0.7s;">
          <div class="p-6">
            <h3 class="text-xl font-bold text-white mb-6 text-center">APX Token Staking</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <!-- Staking Option 1 -->
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700 hover:border-yellow-500 transition-all duration-300">
                <div class="text-center mb-4">
                  <span class="text-sm text-gray-400">Flexible</span>
                  <h4 class="text-xl font-bold text-white">5% APY</h4>
                  <p class="text-xs text-gray-400">No Lock Period</p>
                </div>

                <ul class="space-y-2 mb-4 text-sm">
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Withdraw anytime</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Daily rewards</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">No minimum amount</span>
                  </li>
                </ul>

                <button class="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded-lg transition duration-300 text-sm">
                  Stake Now
                </button>
              </div>

              <!-- Staking Option 2 -->
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-yellow-500 transform scale-105 shadow-lg relative">
                <div class="absolute top-0 right-0 bg-yellow-500 text-black text-xs font-bold px-2 py-1">
                  Popular
                </div>

                <div class="text-center mb-4">
                  <span class="text-sm text-gray-400">Medium Term</span>
                  <h4 class="text-xl font-bold text-white">10% APY</h4>
                  <p class="text-xs text-gray-400">3 Month Lock</p>
                </div>

                <ul class="space-y-2 mb-4 text-sm">
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Higher yield rate</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Weekly rewards</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Min 100 APX tokens</span>
                  </li>
                </ul>

                <button class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-2 px-4 rounded-lg transition duration-300 text-sm">
                  Stake Now
                </button>
              </div>

              <!-- Staking Option 3 -->
              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700 hover:border-yellow-500 transition-all duration-300">
                <div class="text-center mb-4">
                  <span class="text-sm text-gray-400">Long Term</span>
                  <h4 class="text-xl font-bold text-white">15% APY</h4>
                  <p class="text-xs text-gray-400">6 Month Lock</p>
                </div>

                <ul class="space-y-2 mb-4 text-sm">
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Maximum yield</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Monthly rewards</span>
                  </li>
                  <li class="flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    <span class="text-gray-300">Min 250 APX tokens</span>
                  </li>
                </ul>

                <button class="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded-lg transition duration-300 text-sm">
                  Stake Now
                </button>
              </div>
            </div>

            <div class="bg-gray-900 p-4 rounded-lg border border-gray-700">
              <div class="flex items-start">
                <i class="fas fa-info-circle text-yellow-500 mt-1 mr-3"></i>
                <p class="text-gray-300 text-sm">
                  Staking rewards are calculated daily and distributed based on your selected plan. You can compound your earnings by automatically reinvesting rewards for even greater returns. All staking activities are visible in your dashboard for complete transparency.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Leaderboard Section -->
  <div id="leaderboard" class="over py-16 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-white mb-4">Leaderboard & Rewards</h2>
        <p class="text-gray-400 max-w-2xl mx-auto">
          Compete with other investors and earn additional bonuses based on your performance
        </p>
      </div>

      <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center mb-16">
          <div class="slide-up" style="animation-delay: 0.3s;">
            <h3 class="text-2xl font-bold text-yellow-500 mb-6">The Competition</h3>
            <p class="text-gray-300 mb-6">
              Our monthly leaderboard showcases top investors based on total investment value, referrals, and active participation. Top performers receive exclusive rewards, bonuses, and recognition.
            </p>

            <div class="space-y-6">
              <div class="bg-gray-800 p-3 rounded-lg flex items-start">
                <i class="fas fa-trophy text-yellow-500 mt-1 mr-3"></i>
                <div>
                  <h4 class="font-bold text-white mb-1">Monthly Rewards</h4>
                  <p class="text-gray-300 text-sm">
                    Top performers receive bonus APX tokens, higher staking rates, and VIP account upgrades.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-3 rounded-lg flex items-start">
                <i class="fas fa-medal text-yellow-500 mt-1 mr-3"></i>
                <div>
                  <h4 class="font-bold text-white mb-1">Ranking System</h4>
                  <p class="text-gray-300 text-sm">
                    Climb through Bronze, Silver, Gold, Platinum, and Diamond tiers with increasing rewards at each level.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-3 rounded-lg flex items-start">
                <i class="fas fa-gift text-yellow-500 mt-1 mr-3"></i>
                <div>
                  <h4 class="font-bold text-white mb-1">Special Promotions</h4>
                  <p class="text-gray-300 text-sm">
                    Participate in limited-time challenges and community events for exclusive rewards.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="relative scale-in" style="animation-delay: 0.5s;">
            <!-- Leaderboard Visualization -->
            <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-4 sm:p-6 rounded-xl shadow-xl border border-gray-700">
              <div class="flex justify-between items-center mb-4 sm:mb-6">
                <h3 class="text-lg sm:text-xl font-bold text-white">Current Leaders</h3>
                <span class="text-xs sm:text-sm text-gray-400">February 2025</span>
              </div>

              <!-- Top 3 -->
              <div class="flex justify-between mb-6 sm:mb-8">
                <!-- 2nd Place -->
                <div class="text-center px-1 sm:px-2">
                  <div class="relative mb-2">
                    <div class="h-12 w-12 sm:h-16 sm:w-16 md:h-20 md:w-20 rounded-full bg-gray-700 flex items-center justify-center mx-auto border-2 border-gray-500">
                      <i class="fas fa-user-tie text-base sm:text-xl md:text-2xl text-gray-300"></i>
                    </div>
                    <div class="absolute -bottom-1 -right-1 h-5 w-5 sm:h-6 sm:w-6 md:h-8 md:w-8 rounded-full bg-gray-800 border-2 border-gray-600 flex items-center justify-center">
                      <i class="fas fa-medal text-gray-300 text-xs sm:text-sm"></i>
                    </div>
                  </div>
                  <p class="font-bold text-white text-xs sm:text-sm">JohnD</p>
                  <p class="text-[10px] sm:text-xs text-gray-400">₹ 85,200</p>
                </div>

                <!-- 1st Place -->
                <div class="text-center transform scale-105 sm:scale-110 px-1 sm:px-2">
                  <div class="relative mb-2">
                    <div class="h-14 w-14 sm:h-18 sm:w-18 md:h-20 md:w-20 rounded-full bg-gray-700 flex items-center justify-center mx-auto border-2 border-yellow-500">
                      <i class="fas fa-user-tie text-base sm:text-xl md:text-2xl text-yellow-500"></i>
                    </div>
                    <div class="absolute -bottom-1 -right-1 h-5 w-5 sm:h-6 sm:w-6 md:h-8 md:w-8 rounded-full bg-gray-800 border-2 border-yellow-500 flex items-center justify-center">
                      <i class="fas fa-crown text-yellow-500 text-xs sm:text-sm"></i>
                    </div>
                  </div>
                  <p class="font-bold text-yellow-500 text-xs sm:text-sm">SarahW</p>
                  <p class="text-[10px] sm:text-xs text-gray-400">₹ 124,800</p>
                </div>

                <!-- 3rd Place -->
                <div class="text-center px-1 sm:px-2">
                  <div class="relative mb-2">
                    <div class="h-12 w-12 sm:h-16 sm:w-16 md:h-20 md:w-20 rounded-full bg-gray-700 flex items-center justify-center mx-auto border-2 border-yellow-700">
                      <i class="fas fa-user-tie text-base sm:text-xl md:text-2xl text-yellow-700"></i>
                    </div>
                    <div class="absolute -bottom-1 -right-1 h-5 w-5 sm:h-6 sm:w-6 md:h-8 md:w-8 rounded-full bg-gray-800 border-2 border-yellow-700 flex items-center justify-center">
                      <i class="fas fa-award text-yellow-700 text-xs sm:text-sm"></i>
                    </div>
                  </div>
                  <p class="font-bold text-yellow-700 text-xs sm:text-sm">MikeT</p>
                  <p class="text-[10px] sm:text-xs text-gray-400">₹ 67,500</p>
                </div>
              </div>

              <!-- Other Top Performers -->
              <div class="space-y-1 sm:space-y-2">
                <div class="leader-item flex justify-between items-center p-2 sm:p-3 rounded-lg transition-all duration-300">
                  <div class="flex items-center">
                    <div class="number-circle w-5 h-5 sm:w-6 sm:h-6 text-xs sm:text-sm mr-2 sm:mr-3 flex-shrink-0">4</div>
                    <span class="text-xs sm:text-sm text-gray-300">AlexJ</span>
                  </div>
                  <span class="text-xs sm:text-sm text-yellow-500">₹ 52,300</span>
                </div>

                <div class="leader-item flex justify-between items-center p-2 sm:p-3 rounded-lg transition-all duration-300">
                  <div class="flex items-center">
                    <div class="number-circle w-5 h-5 sm:w-6 sm:h-6 text-xs sm:text-sm mr-2 sm:mr-3 flex-shrink-0">5</div>
                    <span class="text-xs sm:text-sm text-gray-300">LisaP</span>
                  </div>
                  <span class="text-xs sm:text-sm text-yellow-500">₹ 48,700</span>
                </div>

                <div class="leader-item flex justify-between items-center p-2 sm:p-3 rounded-lg transition-all duration-300">
                  <div class="flex items-center">
                    <div class="number-circle w-5 h-5 sm:w-6 sm:h-6 text-xs sm:text-sm mr-2 sm:mr-3 flex-shrink-0">6</div>
                    <span class="text-xs sm:text-sm text-gray-300">DavidM</span>
                  </div>
                  <span class="text-xs sm:text-sm text-yellow-500">₹ 41,200</span>
                </div>
              </div>

              <div class="mt-4 sm:mt-6 text-center">
                <span class="text-xs sm:text-sm text-gray-400">Your Rank: </span>
                <span class="text-xs sm:text-sm text-white font-bold">14</span>
                <div class="mt-2 sm:mt-3">
                  <button class="px-3 sm:px-4 py-1 sm:py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-xs sm:text-sm transition duration-300">
                    View Full Leaderboard
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Ranking System -->
        <div class="bg-gray-800 bg-opacity-50 rounded-xl overflow-hidden shadow-xl border border-gray-700 slide-up" style="animation-delay: 0.7s;">
          <div class="p-6">
            <h3 class="text-xl font-bold text-white mb-6 text-center">Ranking System & Benefits</h3>

            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="border-b border-gray-700">
                    <th class="py-3 px-4 text-left">Rank</th>
                    <th class="py-3 px-4 text-center">Requirements</th>
                    <th class="py-3 px-4 text-center">Monthly Rewards</th>
                    <th class="py-3 px-4 text-right">Special Benefits</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="border-b border-gray-700">
                    <td class="py-3 px-4">
                      <div class="flex items-center">
                        <div class="h-6 w-6 rounded-full bg-yellow-500 mr-2 flex items-center justify-center">
                          <i class="fas fa-diamond text-black text-xs"></i>
                        </div>
                        <span class="font-bold">Diamond</span>
                      </div>
                    </td>
                    <td class="py-3 px-4 text-center">₹ 100,000+ invested</td>
                    <td class="py-3 px-4 text-center">500 APX + 2% bonus</td>
                    <td class="py-3 px-4 text-right">VIP events, 1-hr withdrawals</td>
                  </tr>
                  <tr class="border-b border-gray-700">
                    <td class="py-3 px-4">
                      <div class="flex items-center">
                        <div class="h-6 w-6 rounded-full bg-gray-300 mr-2 flex items-center justify-center">
                          <i class="fas fa-gem text-gray-700 text-xs"></i>
                        </div>
                        <span class="font-bold">Platinum</span>
                      </div>
                    </td>
                    <td class="py-3 px-4 text-center">₹ 50,000+ invested</td>
                    <td class="py-3 px-4 text-center">250 APX + 1.5% bonus</td>
                    <td class="py-3 px-4 text-right">Priority support, 3-hr withdrawals</td>
                  </tr>
                  <tr class="border-b border-gray-700">
                    <td class="py-3 px-4">
                      <div class="flex items-center">
                        <div class="h-6 w-6 rounded-full bg-yellow-600 mr-2 flex items-center justify-center">
                          <i class="fas fa-medal text-black text-xs"></i>
                        </div>
                        <span class="font-bold">Gold</span>
                      </div>
                    </td>
                    <td class="py-3 px-4 text-center">₹ 25,000+ invested</td>
                    <td class="py-3 px-4 text-center">100 APX + 1% bonus</td>
                    <td class="py-3 px-4 text-right">Enhanced referral rates</td>
                  </tr>
                  <tr class="border-b border-gray-700">
                    <td class="py-3 px-4">
                      <div class="flex items-center">
                        <div class="h-6 w-6 rounded-full bg-gray-400 mr-2 flex items-center justify-center">
                          <i class="fas fa-medal text-black text-xs"></i>
                        </div>
                        <span class="font-bold">Silver</span>
                      </div>
                    </td>
                    <td class="py-3 px-4 text-center">₹ 10,000+ invested</td>
                    <td class="py-3 px-4 text-center">50 APX + 0.5% bonus</td>
                    <td class="py-3 px-4 text-right">Expedited withdrawals</td>
                  </tr>
                  <tr>
                    <td class="py-3 px-4">
                      <div class="flex items-center">
                        <div class="h-6 w-6 rounded-full bg-yellow-800 mr-2 flex items-center justify-center">
                          <i class="fas fa-medal text-black text-xs"></i>
                        </div>
                        <span class="font-bold">Bronze</span>
                      </div>
                    </td>
                    <td class="py-3 px-4 text-center">₹ 3,000+ invested</td>
                    <td class="py-3 px-4 text-center">10 APX tokens</td>
                    <td class="py-3 px-4 text-right">Standard features</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Customer Support Section -->
  <div id="support" class="over over py-16 bg-black">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-white mb-4">24/7 Customer Support</h2>
        <p class="text-gray-400 max-w-2xl mx-auto">
          Our dedicated support team is always available to assist you with any questions or issues
        </p>
      </div>

      <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
          <div class="slide-up" style="animation-delay: 0.3s;">
            <h3 class="text-2xl font-bold text-yellow-500 mb-6">We're Here For You</h3>
            <p class="text-gray-300 mb-6">
              AutoProftX provides comprehensive support through multiple channels to ensure that all your questions are answered promptly and efficiently.
            </p>

            <div class="space-y-6">
              <div class="bg-gray-800 p-4 rounded-lg flex items-start">
                <div class="flex-shrink-0 h-12 w-12 flex items-center justify-center rounded-full bg-gray-700 text-yellow-500 mr-4">
                  <i class="fas fa-comments"></i>
                </div>
                <div>
                  <h4 class="font-bold text-white mb-1">Live Chat Support</h4>
                  <p class="text-gray-300 text-sm">
                    Connect instantly with our support team through the chat widget available on your dashboard. Available 24/7 with response times under 2 minutes.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-4 rounded-lg flex items-start">
                <div class="flex-shrink-0 h-12 w-12 flex items-center justify-center rounded-full bg-gray-700 text-yellow-500 mr-4">
                  <i class="fas fa-envelope"></i>
                </div>
                <div>
                  <h4 class="font-bold text-white mb-1">Email Support</h4>
                  <p class="text-gray-300 text-sm">
                    Send detailed inquiries to <span class="text-yellow-500">support@autoproftx.com</span> and receive comprehensive responses within 4 hours.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-4 rounded-lg flex items-start">
                <div class="flex-shrink-0 h-12 w-12 flex items-center justify-center rounded-full bg-gray-700 text-yellow-500 mr-4">
                  <i class="fas fa-book"></i>
                </div>
                <div>
                  <h4 class="font-bold text-white mb-1">Knowledge Base</h4>
                  <p class="text-gray-300 text-sm">
                    Access our extensive library of guides, tutorials, and FAQs covering all aspects of our platform.
                  </p>
                </div>
              </div>

              <div class="bg-gray-800 p-4 rounded-lg flex items-start">
                <div class="flex-shrink-0 h-12 w-12 flex items-center justify-center rounded-full bg-gray-700 text-yellow-500 mr-4">
                  <i class="fas fa-phone-alt"></i>
                </div>
                <div>
                  <h4 class="font-bold text-white mb-1">Premium Phone Support</h4>
                  <p class="text-gray-300 text-sm">
                    Premium and Professional plan members receive dedicated phone support with personal account managers.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="relative scale-in" style="animation-delay: 0.5s;">
            <!-- Support Chat Visualization -->
            <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-6 rounded-xl shadow-xl border border-gray-700">
              <div class="flex justify-between items-center mb-6">
                <div class="flex items-center">
                  <div class="h-10 w-10 rounded-full bg-yellow-500 flex items-center justify-center mr-3">
                    <i class="fas fa-headset text-black"></i>
                  </div>
                  <div>
                    <h4 class="font-bold text-white">Support Chat</h4>
                    <p class="text-xs text-gray-400">Online • Sarah (Support Agent)</p>
                  </div>
                </div>
                <span class="text-xs text-gray-400">Now</span>
              </div>

              <!-- Chat Messages -->
              <div class="space-y-4 mb-6">
                <div class="chat-bubble ml-6">
                  <p class="text-sm text-white">Hello! How can I help you today with your AutoProftX account?</p>
                </div>

                <div class="chat-bubble ml-6">
                  <p class="text-sm text-white">I can assist with investment plans, withdrawals, referrals, or any technical issues you might be experiencing.</p>
                </div>

                <div class="bg-yellow-500 text-black p-3 rounded-lg mr-6 ml-auto">
                  <p class="text-sm">I'd like to know how to invite friends to earn referral bonuses.</p>
                </div>

                <div class="chat-bubble ml-6">
                  <p class="text-sm text-white">Great question! You can find your unique referral link in the "Referrals" section of your dashboard. You can share this link via email, social media, or messaging apps.</p>
                </div>

                <div class="chat-bubble ml-6">
                  <p class="text-sm text-white">When someone signs up and invests using your link, you'll automatically receive a 7% commission on their investment amount, credited directly to your account balance.</p>
                </div>

                <div class="bg-yellow-500 text-black p-3 rounded-lg mr-6 ml-auto">
                  <p class="text-sm">That's perfect, thank you!</p>
                </div>
              </div>

              <!-- Chat Input -->
              <div class="relative">
                <input type="text" placeholder="Type your message..." class="w-full bg-gray-700 rounded-lg py-3 px-4 pr-12 text-white focus:outline-none border border-gray-600 focus:border-yellow-500">
                <button class="absolute right-4 top-1/2 transform -translate-y-1/2 text-yellow-500">
                  <i class="fas fa-paper-plane"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Success Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-16">
          <div class="bg-gray-800 bg-opacity-50 p-6 rounded-xl border border-gray-700 text-center slide-up" style="animation-delay: 0.6s;">
            <div class="text-4xl font-bold text-yellow-500 mb-2">98.7%</div>
            <p class="text-gray-300">Customer Satisfaction</p>
            <div class="flex justify-center mt-3 text-yellow-500">
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
            </div>
          </div>

          <div class="bg-gray-800 bg-opacity-50 p-6 rounded-xl border border-gray-700 text-center slide-up" style="animation-delay: 0.7s;">
            <div class="text-4xl font-bold text-yellow-500 mb-2">
              < 2min</div>
                <p class="text-gray-300">Average Response Time</p>
                <div class="flex justify-center mt-3 text-yellow-500">
                  <i class="fas fa-bolt"></i>
                </div>
            </div>

            <div class="bg-gray-800 bg-opacity-50 p-6 rounded-xl border border-gray-700 text-center slide-up" style="animation-delay: 0.8s;">
              <div class="text-4xl font-bold text-yellow-500 mb-2">24/7</div>
              <p class="text-gray-300">Support Availability</p>
              <div class="flex justify-center mt-3 text-yellow-500">
                <i class="fas fa-clock"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Deposit & Withdrawal Section -->
    <div class="over py-16 bg-gradient-to-b from-gray-900 to-black">
      <div class="container mx-auto px-6">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-bold text-white mb-4">Simple Deposits & Withdrawals</h2>
          <p class="text-gray-400 max-w-2xl mx-auto">
            Manage your funds with ease using our secure and efficient payment system
          </p>
        </div>

        <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-12">
          <!-- Deposit Methods -->
          <div class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-xl overflow-hidden shadow-xl border border-gray-700 slide-in-left" style="animation-delay: 0.3s;">
            <div class="p-6">
              <div class="flex items-center mb-6">
                <div class="h-12 w-12 rounded-full bg-gray-700 flex items-center justify-center text-yellow-500 mr-4">
                  <i class="fas fa-arrow-down"></i>
                </div>
                <h3 class="text-xl font-bold text-white">Deposit Methods</h3>
              </div>

              <div class="space-y-4">
                <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700 flex items-center">
                  <div class="h-10 w-10 rounded-lg bg-gray-700 flex items-center justify-center mr-4">
                    <i class="fas fa-credit-card text-yellow-500"></i>
                  </div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Credit/Debit Cards</h4>
                    <p class="text-gray-400 text-xs">Instant • Min: ₹ 3,000 • Max: ₹ 100,000</p>
                  </div>
                  <div class="ml-auto">
                    <button class="px-4 py-1 bg-yellow-500 hover:bg-yellow-600 rounded-lg text-sm text-black transition duration-300">
                      Deposit
                    </button>
                  </div>
                </div>

                <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700 flex items-center">
                  <div class="h-10 w-10 rounded-lg bg-gray-700 flex items-center justify-center mr-4">
                    <i class="fas fa-university text-yellow-500"></i>
                  </div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Bank Transfer</h4>
                    <p class="text-gray-400 text-xs">1-2 Hours • Min: ₹ 5,000 • No Max</p>
                  </div>
                  <div class="ml-auto">
                    <button class="px-4 py-1 bg-yellow-500 hover:bg-yellow-600 rounded-lg text-sm text-black transition duration-300">
                      Deposit
                    </button>
                  </div>
                </div>

                <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700 flex items-center">
                  <div class="h-10 w-10 rounded-lg bg-gray-700 flex items-center justify-center mr-4">
                    <i class="fab fa-bitcoin text-yellow-500"></i>
                  </div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Cryptocurrency</h4>
                    <p class="text-gray-400 text-xs">10-30 Min • Min: $50 Equivalent • No Max</p>
                  </div>
                  <div class="ml-auto">
                    <button class="px-4 py-1 bg-yellow-500 hover:bg-yellow-600 rounded-lg text-sm text-black transition duration-300">
                      Deposit
                    </button>
                  </div>
                </div>

                <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700 flex items-center">
                  <div class="h-10 w-10 rounded-lg bg-gray-700 flex items-center justify-center mr-4">
                    <i class="fas fa-wallet text-yellow-500"></i>
                  </div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">E-Wallets</h4>
                    <p class="text-gray-400 text-xs">Instant • Min: ₹ 3,000 • Max: ₹ 50,000</p>
                  </div>
                  <div class="ml-auto">
                    <button class="px-4 py-1 bg-yellow-500 hover:bg-yellow-600 rounded-lg text-sm text-black transition duration-300">
                      Deposit
                    </button>
                  </div>
                </div>
              </div>

              <div class="mt-6 bg-gray-900 p-4 rounded-lg">
                <div class="flex items-start">
                  <i class="fas fa-shield-alt text-yellow-500 mt-1 mr-3"></i>
                  <p class="text-gray-300 text-sm">
                    All transactions are protected with bank-grade encryption and advanced security protocols. We never store your payment information.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Withdrawal Process -->
          <div class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-xl overflow-hidden shadow-xl border border-gray-700 slide-in-right" style="animation-delay: 0.3s;">
            <div class="p-6">
              <div class="flex items-center mb-6">
                <div class="h-12 w-12 rounded-full bg-gray-700 flex items-center justify-center text-yellow-500 mr-4">
                  <i class="fas fa-arrow-up"></i>
                </div>
                <h3 class="text-xl font-bold text-white">Withdrawal Process</h3>
              </div>

              <div class="space-y-4 mb-6">
                <div class="flex items-start">
                  <div class="number-circle mr-4 flex-shrink-0">1</div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Request Withdrawal</h4>
                    <p class="text-gray-300 text-sm">
                      Navigate to the withdrawals section in your dashboard and enter the amount you wish to withdraw.
                    </p>
                  </div>
                </div>

                <div class="flex items-start">
                  <div class="number-circle mr-4 flex-shrink-0">2</div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Select Payment Method</h4>
                    <p class="text-gray-300 text-sm">
                      Choose your preferred withdrawal method from the available options (bank transfer, crypto, e-wallet).
                    </p>
                  </div>
                </div>

                <div class="flex items-start">
                  <div class="number-circle mr-4 flex-shrink-0">3</div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Verification</h4>
                    <p class="text-gray-300 text-sm">
                      Confirm the withdrawal via email or SMS verification code for enhanced security.
                    </p>
                  </div>
                </div>

                <div class="flex items-start">
                  <div class="number-circle mr-4 flex-shrink-0">4</div>
                  <div>
                    <h4 class="font-bold text-white text-sm mb-1">Processing & Receipt</h4>
                    <p class="text-gray-300 text-sm">
                      Withdrawals are processed within 24 hours (1-6 hours for premium members). You'll receive a confirmation once complete.
                    </p>
                  </div>
                </div>
              </div>

              <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border border-gray-700">
                <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-700">
                  <span class="text-gray-300">Available for Withdrawal:</span>
                  <span class="text-xl font-bold text-yellow-500">₹ 12,500</span>
                </div>

                <div class="flex justify-between items-center mb-2">
                  <span class="text-gray-300">Minimum Withdrawal:</span>
                  <span class="text-white">₹ 1,000</span>
                </div>

                <div class="flex justify-between items-center mb-4">
                  <span class="text-gray-300">Processing Time:</span>
                  <span class="text-white">Up to 24 Hours</span>
                </div>

                <button class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-2 px-4 rounded-lg transition duration-300">
                  Withdraw Funds
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="over py-16 bg-black">
      <div class="container mx-auto px-6">
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-gray-900 to-black rounded-xl p-8 shadow-2xl border border-gray-800 relative overflow-hidden">
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-yellow-500/10 via-transparent to-transparent"></div>
          <div class="relative z-10">
            <div class="text-center mb-8">
              <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Start Your Investment Journey Today</h2>
              <p class="text-lg text-gray-300 max-w-2xl mx-auto">
                Join thousands of investors already earning consistent 20% returns with AutoProftX
              </p>
            </div>
            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
              <button onclick="window.location.href='register.php'" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105 shadow-xl">
                Create Account
              </button>
              <button class="bg-transparent hover:bg-gray-800 text-yellow-500 font-bold py-3 px-8 border border-yellow-500 rounded-lg transition duration-300">
                View Investment Plans
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="bg-black border-t border-gray-800 py-6 sm:py-8">
      <div class="container mx-auto px-4 sm:px-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <!-- Logo -->
          <div class="flex items-center mb-6 md:mb-0">
            <i class="fas fa-gem text-yellow-500 text-lg sm:text-xl mr-2"></i>
            <span class="text-lg sm:text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">AutoProftX</span>
          </div>

          <!-- Navigation Links - Mobile-optimized with grid for small screens -->
          <div class="grid grid-cols-3 gap-x-4 gap-y-3 sm:flex sm:space-x-4 md:space-x-8 mb-6 md:mb-0 text-center sm:text-left">
            <a href="index.php" class="text-xs sm:text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Home</a>
            <a href="about.php" class="text-xs sm:text-sm text-gray-400 hover:text-yellow-500 transition duration-300">About</a>
            <a href="how-it-works.php" class="text-xs sm:text-sm text-yellow-500 transition duration-300">How It Works</a>
            <a href="#" class="text-xs sm:text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Terms</a>
            <a href="#" class="text-xs sm:text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Privacy</a>
            <a href="#" class="text-xs sm:text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Support</a>
          </div>

          <!-- Social Media Icons -->
          <div class="flex space-x-6 sm:space-x-4">
            <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-facebook-f text-sm sm:text-base"></i></a>
            <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-twitter text-sm sm:text-base"></i></a>
            <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-instagram text-sm sm:text-base"></i></a>
            <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-linkedin-in text-sm sm:text-base"></i></a>
          </div>
        </div>

        <!-- Copyright -->
        <div class="text-center text-gray-600 text-xs sm:text-sm mt-6 sm:mt-8">
          &copy; 2025 AutoProftX. All rights reserved.
        </div>
      </div>
    </footer>

    <!-- Scroll to top button -->
    <div id="scrollTopBtn" class="fixed bottom-8 right-10 w-10 h-10 bg-yellow-500 text-black rounded-full flex items-center justify-center cursor-pointer opacity-0 transition-opacity duration-300 shadow-lg hover:bg-yellow-600">
      <i class="fas fa-arrow-up" style="position: absolute;left:13px;top:12px;"></i>
    </div>

    <?php include 'includes/js-links.php'; ?>

    <script>
      // Show/hide scroll to top button
      window.addEventListener('scroll', function() {
        const scrollBtn = document.getElementById('scrollTopBtn');
        if (window.pageYOffset > 300) {
          scrollBtn.style.opacity = '1';
        } else {
          scrollBtn.style.opacity = '0';
        }
      });

      // Scroll to top when button is clicked
      document.getElementById('scrollTopBtn').addEventListener('click', function() {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      });

      // Animation on scroll
      const animateElements = document.querySelectorAll('.scale-in, .fade-in, .slide-up, .slide-in-left, .slide-in-right');

      const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      };

      const observer = new IntersectionObserver(function(entries, observer) {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.animationPlayState = 'running';
            observer.unobserve(entry.target);
          }
        });
      }, observerOptions);

      animateElements.forEach(element => {
        element.style.animationPlayState = 'paused';
        observer.observe(element);
      });

      // Custom plan calculator
      const customAmountInput = document.getElementById('custom-amount');
      if (customAmountInput) {
        customAmountInput.addEventListener('input', function() {
          const amount = parseFloat(this.value) || 25000;
          const profit = amount * 0.2;
          const total = amount + profit;

          document.getElementById('investment-display').textContent = `₹${amount.toLocaleString()}`;
          document.getElementById('profit-display').textContent = `₹${profit.toLocaleString()}`;
          document.getElementById('return-display').textContent = `₹${total.toLocaleString()}`;
        });
      }
    </script>
</body>

</html>