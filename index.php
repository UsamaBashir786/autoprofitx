<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php' ?>
  <style>
    @media (max-width: 768px) {
      .scroll-to-top {
        display: none !important;
      }
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
  </style>
</head>

<body class="bg-black text-white font-sans">
  <?php include 'includes/navbar.php' ?>

  <!-- Premium Hero Section with SVGs -->
  <div class="relative py-16 md:py-24 overflow-hidden">
    <div class="absolute inset-0 z-0">
      <div class="absolute inset-0 bg-gradient-to-b from-black to-gray-900"></div>
      <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-gray-700/20 via-gray-900/40 to-black"></div>

      <!-- Background SVG Elements -->
      <!-- Abstract connection lines -->
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
    <!-- Gold coin svg 1 -->
    <div class="absolute left-1/4 top-20 opacity-70 w-20 h-20 floating-slow hidden md:block">
      <svg viewBox="0 0 100 100" class="pulse-gold">
        <circle cx="50" cy="50" r="45" fill="#111" stroke="#f59e0b" stroke-width="2" />
        <circle cx="50" cy="50" r="40" fill="url(#goldGradient)" />
        <text x="50" y="55" text-anchor="middle" fill="#111" font-weight="bold" font-size="24">₹</text>
      </svg>
    </div>

    <!-- Growth chart svg -->
    <div class="absolute right-1/4 bottom-20 opacity-70 w-32 h-32 floating hidden md:block">
      <svg viewBox="0 0 100 100" class="pulse-gold">
        <rect x="10" y="10" width="80" height="80" rx="5" fill="#111" stroke="#f59e0b" stroke-width="1" />
        <path d="M20,70 L30,50 L45,60 L60,40 L80,20" stroke="#f59e0b" stroke-width="3" fill="none" class="glow-path" stroke-linecap="round" />
        <circle cx="30" cy="50" r="3" fill="#f59e0b" />
        <circle cx="45" cy="60" r="3" fill="#f59e0b" />
        <circle cx="60" cy="40" r="3" fill="#f59e0b" />
        <circle cx="80" cy="20" r="3" fill="#f59e0b" />
      </svg>
    </div>

    <!-- Circular stats element -->
    <div class="absolute left-1/5 bottom-10 opacity-70 w-24 h-24 floating-fast hidden md:block">
      <svg viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="45" fill="none" stroke="#222" stroke-width="8" />
        <circle cx="50" cy="50" r="45" fill="none" stroke="#f59e0b" stroke-width="8" stroke-dasharray="283" stroke-dashoffset="56" transform="rotate(-90 50 50)" />
        <text x="50" y="45" text-anchor="middle" fill="white" font-weight="bold" font-size="18">20%</text>
        <text x="50" y="65" text-anchor="middle" fill="#f59e0b" font-size="12">Return</text>
      </svg>
    </div>

    <!-- Gold coin svg 2 -->
    <div class="absolute right-1/5 top-1/3 opacity-70 w-16 h-16 floating-fast hidden md:block">
      <svg viewBox="0 0 100 100" class="pulse-gold">
        <defs>
          <linearGradient id="goldGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f7e084" />
            <stop offset="50%" stop-color="#f59e0b" />
            <stop offset="100%" stop-color="#e67e00" />
          </linearGradient>
        </defs>
        <circle cx="50" cy="50" r="45" fill="#111" stroke="#f59e0b" stroke-width="2" />
        <circle cx="50" cy="50" r="40" fill="url(#goldGradient)" />
        <text x="50" y="55" text-anchor="middle" fill="#111" font-weight="bold" font-size="24">₹</text>
      </svg>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 relative z-10">
      <div class="max-w-3xl mx-auto text-center">
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
          <span class="block">Exclusive Investment</span>
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">Opportunities</span>
        </h1>

        <p class="text-lg text-gray-300 mb-8 max-w-xl mx-auto">
          Access premium profit plans designed for discerning investors seeking exceptional returns
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
          <a href="#plans" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-black bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 md:py-4 md:text-lg md:px-10 transition duration-300 transform hover:scale-105 shadow-xl">
            <svg width="24" height="24" viewBox="0 0 24 24" class="mr-2" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 8V16M8 12H16M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Explore Plans
          </a>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/charts.php' ?>

  <!-- Premium Features -->
  <div class="bg-gradient-to-b from-black to-gray-900 py-12">
    <div class="container mx-auto px-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-6 rounded-xl shadow-xl border border-gray-700 transform transition-all duration-300 hover:border-yellow-500">
          <div class="flex items-center justify-center h-14 w-14 rounded-md bg-gray-800 border border-yellow-500 text-yellow-500 mb-5">
            <i class="fas fa-lock text-xl"></i>
          </div>
          <h3 class="text-lg font-bold text-white mb-3">Secured Investment</h3>
          <p class="text-gray-400">
            All investments are backed by our security guarantee for maximum protection and peace of mind.
          </p>
        </div>

        <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-6 rounded-xl shadow-xl border border-gray-700 transform transition-all duration-300 hover:border-yellow-500">
          <div class="flex items-center justify-center h-14 w-14 rounded-md bg-gray-800 border border-yellow-500 text-yellow-500 mb-5">
            <i class="fas fa-chart-line text-xl"></i>
          </div>
          <h3 class="text-lg font-bold text-white mb-3">Consistent Returns</h3>
          <p class="text-gray-400">
            Our sophisticated algorithms ensure reliable profit generation across all market conditions.
          </p>
        </div>

        <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-6 rounded-xl shadow-xl border border-gray-700 transform transition-all duration-300 hover:border-yellow-500">
          <div class="flex items-center justify-center h-14 w-14 rounded-md bg-gray-800 border border-yellow-500 text-yellow-500 mb-5">
            <i class="fas fa-user-shield text-xl"></i>
          </div>
          <h3 class="text-lg font-bold text-white mb-3">VIP Support</h3>
          <p class="text-gray-400">
            Dedicated account managers and 24/7 priority support for all our elite members.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Profit Plans Section -->
  <div id="plans" class="py-16 bg-black">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-white mb-4">Investment Plans</h2>
        <p class="text-gray-400 max-w-xl mx-auto">
          Select the investment tier that aligns with your financial goals - All plans offer 20% returns
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
        <!-- Basic Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group">
          <div class="p-8">
            <h3 class="text-xl font-bold text-white mb-4">Basic</h3>
            <div class="flex items-baseline mb-6">
              <span class="text-4xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-6">Perfect for beginners looking to start their investment journey.</p>

            <ul class="space-y-4 mb-8">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">3,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Proft after 24 Hours</span>
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

        <!-- Premium Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl overflow-hidden shadow-2xl border border-yellow-500 transform scale-105 relative">
          <div class="absolute top-0 right-0 bg-yellow-500 text-black font-bold px-4 py-1">
            Most Popular
          </div>
          <div class="p-8">
            <h3 class="text-xl font-bold text-white mb-4">Premium</h3>
            <div class="flex items-baseline mb-6">
              <span class="text-4xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-6">Optimized returns for serious investors with exclusive benefits.</p>

            <ul class="space-y-4 mb-8">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">10,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Proft after 24 Hours</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Priority support</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Early access to new plans</span>
              </li>
            </ul>

            <button class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
              Get Started
            </button>
          </div>
        </div>

        <!-- Standard Plan -->
        <div class="bg-gray-800 bg-opacity-50 backdrop-filter backdrop-blur-sm rounded-xl shadow-lg overflow-hidden border border-gray-700 hover:border-yellow-500 transition-all duration-300 card-hover">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Standard</h3>
              <span class="bg-green-800 rounded-full px-3 py-1 text-sm font-semibold">Popular</span>
            </div>
            <div class="mb-4 flex items-baseline">
              <span class="text-3xl font-bold">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <ul class="space-y-2 mb-6">
              <li class="flex items-center">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">5,000 PKR minimum investment</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Profit after 24 hours</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Enhanced support</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Email notifications</span>
              </li>
            </ul>
            <button onclick="activatePlan('Standard', 5000)" class="w-full bg-gray-700 hover:bg-gradient-to-r hover:from-yellow-500 hover:to-yellow-600 hover:text-black text-white font-bold py-3 px-4 rounded-lg transition duration-300">
              Buy Now
            </button>
          </div>
        </div>

        <!-- Professional Plan -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group">
          <div class="p-8">
            <h3 class="text-xl font-bold text-white mb-4">Professional</h3>
            <div class="flex items-baseline mb-6">
              <span class="text-4xl font-extrabold text-white">20%</span>
              <span class="text-lg text-gray-400 ml-2">return</span>
            </div>
            <p class="text-gray-400 mb-6">Exclusive access to our highest yield investment strategy.</p>

            <ul class="space-y-4 mb-8">
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">20,000 PKR minimum investment</span>
              </li>
              <li class="flex items-start">
                <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                <span class="text-gray-300">Proft after 24 Hours</span>
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


      </div>
      <br><br>
      <div class="mt-10">
        <div id="premium-card" class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-xl shadow-lg overflow-hidden border border-yellow-700 hover:border-yellow-500 transition-all duration-300 premium-card gold-shimmer">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-xl font-bold">Custom Plan</h3>
              <span class="bg-yellow-800 rounded-full px-3 py-1 text-sm font-semibold flex items-center">
                <i class="fas fa-crown text-yellow-400 mr-1 text-xs"></i>
                Exclusive
              </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="float-effect">
                <div class="mb-4 flex items-baseline bg-black bg-opacity-20 p-3 rounded-lg border-l-2 border-yellow-600">
                  <span class="text-3xl font-bold">20%</span>
                  <span class="text-lg text-yellow-400 ml-2">return</span>
                </div>
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
                  <li class="flex items-center">
                    <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                    <span class="text-gray-300">Premium 24/7 support</span>
                  </li>
                </ul>
              </div>
              <div>
                <div class="mb-4">
                  <label for="custom-amount" class="block text-sm font-medium text-yellow-300 mb-2">Investment Amount (PKR)</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-400">Rs:</span>
                    </div>
                    <input type="number" id="custom-amount" min="25000" step="1000" value="25000"
                      class="pl-10 bg-gray-700 border border-gray-600 rounded-md py-2 px-4 w-full text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-glow">
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Minimum: 25,000 PKR</p>
                </div>
                <div class="mb-6">
                  <div class="bg-black bg-opacity-30 rounded-md p-4 border border-gray-800">
                    <div class="flex justify-between items-center mb-2 border-b border-gray-700 pb-2">
                      <span class="text-gray-400">Your investment:</span>
                      <span class="text-white font-bold" id="investment-display">Rs:25,000</span>
                    </div>
                    <div class="flex justify-between items-center mb-2 border-b border-gray-700 pb-2">
                      <span class="text-gray-400">Expected profit:</span>
                      <span class="text-green-500 font-bold" id="profit-display">Rs:5,000</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-gray-400">Total return:</span>
                      <span class="text-yellow-500 font-bold" id="return-display">Rs:30,000</span>
                    </div>
                  </div>
                </div>
                <button id="custom-plan-btn" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg button-shine">
                  Create Custom Plan
                  <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Testimonials Section -->
  <div class="py-16 bg-gradient-to-b from-gray-900 to-black">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-white mb-4">What Our Elite Investors Say</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
        <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-6 rounded-xl shadow-xl border border-gray-700">
          <div class="flex items-center mb-6">
            <div class="h-12 w-12 rounded-full bg-gray-700 flex items-center justify-center text-yellow-500">
              <i class="fas fa-user"></i>
            </div>
            <div class="ml-4">
              <h4 class="text-lg font-bold text-white">James Wilson</h4>
              <p class="text-yellow-500">VIP Member</p>
            </div>
          </div>
          <p class="text-gray-300 italic">
            "The returns from AutoProftX have consistently exceeded my expectations. As a VIP member, the personalized service has been exceptional."
          </p>
          <div class="mt-4 text-yellow-500">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
          </div>
        </div>

        <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-6 rounded-xl shadow-xl border border-gray-700">
          <div class="flex items-center mb-6">
            <div class="h-12 w-12 rounded-full bg-gray-700 flex items-center justify-center text-yellow-500">
              <i class="fas fa-user"></i>
            </div>
            <div class="ml-4">
              <h4 class="text-lg font-bold text-white">Sophia Chen</h4>
              <p class="text-yellow-500">Premium Member</p>
            </div>
          </div>
          <p class="text-gray-300 italic">
            "I've tried several investment platforms, but AutoProftX delivers true value. The 12% weekly return has significantly boosted my portfolio."
          </p>
          <div class="mt-4 text-yellow-500">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA Section -->
  <div class="py-16 bg-black">
    <div class="container mx-auto px-6">
      <div class="max-w-4xl mx-auto bg-gradient-to-r from-gray-900 to-black rounded-xl p-8 shadow-2xl border border-gray-800 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-yellow-500/10 via-transparent to-transparent"></div>
        <div class="relative z-10">
          <div class="text-center mb-8">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Begin Your Wealth Journey Today</h2>
            <p class="text-lg text-gray-300 max-w-2xl mx-auto">
              Join the exclusive community of AutoProftX investors and experience exceptional returns
            </p>
          </div>
          <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
            <button onclick="window.location.href='register.php'" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-8 rounded-lg transition duration-300 transform hover:scale-105 shadow-xl">
              Create Account
            </button>
            <button class="bg-transparent hover:bg-gray-800 text-yellow-500 font-bold py-3 px-8 border border-yellow-500 rounded-lg transition duration-300">
              Learn More
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-black border-t border-gray-800 py-8">
    <div class="container mx-auto px-6">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="flex items-center mb-6 md:mb-0">
          <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
          <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">AutoProftX</span>
        </div>
        <div class="flex space-x-8 mb-6 md:mb-0">
          <a href="#" class="text-sm text-gray-400 hover:text-yellow-500 transition duration-300">About</a>
          <a href="#" class="text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Terms</a>
          <a href="#" class="text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Privacy</a>
          <a href="#" class="text-sm text-gray-400 hover:text-yellow-500 transition duration-300">Support</a>
        </div>
        <div class="flex space-x-4">
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-twitter"></i></a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-instagram"></i></a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>
      <div class="text-center text-gray-600 text-sm mt-8">
        &copy; 2025 AutoProftX. All rights reserved.
      </div>
    </div>
  </footer>
  <div id="scrollTopBtn" class="scroll-to-top">↑</div>
  <?php include 'includes/js-links.php'; ?>
</body>

</html>