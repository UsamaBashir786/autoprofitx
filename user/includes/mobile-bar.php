<!-- Mobile Bottom Navigation Bar -->
<div class="md:hidden fixed bottom-0 left-0 right-0 bg-black border-t border-gray-800 z-40">
  <div class="grid grid-cols-5 h-16">
    <!-- Dashboard -->
    <a href="index.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
      <i class="fas fa-chart-line text-lg"></i>
      <span class="text-xs mt-1">Dashboard</span>
    </a>

    <!-- Add Funds -->
    <a href="deposit.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
      <i class="fas fa-money-bill text-lg"></i>
      <span class="text-xs mt-1">Add Funds</span>
    </a>

    <!-- Invest Button (Center, Highlighted) -->
    <a href="#" onclick="document.getElementById('investModal').classList.remove('hidden')" class="relative flex flex-col items-center justify-center">
      <div class="absolute -top-5 w-16 h-16 rounded-full gold-gradient flex items-center justify-center shadow-lg border-4 border-black">
        <i class="fas fa-plus text-black text-xl"></i>
      </div>
      <span class="text-xs mt-8 text-yellow-500">Invest</span>
    </a>

    <!-- Referrals -->
    <a href="referrals.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
      <i class="fas fa-users text-lg"></i>
      <span class="text-xs mt-1">Referrals</span>
    </a>

    <!-- Menu/More -->
    <button id="bottom-more-btn" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
      <i class="fas fa-bars text-lg"></i>
      <span class="text-xs mt-1">More</span>
    </button>
  </div>
</div>

<!-- Invest Modal -->
<div id="investModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
  <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4 border border-gray-700">
    <div class="flex justify-between items-center mb-6">
      <h3 class="text-xl font-bold text-white">Quick Invest</h3>
      <button onclick="document.getElementById('investModal').classList.add('hidden')" class="text-gray-400 hover:text-white">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
      <a href="#" onclick="activatePlan('Basic', 10); document.getElementById('investModal').classList.add('hidden')" class="flex flex-col items-center justify-center bg-gray-700 hover:bg-gray-600 p-4 rounded-lg transition duration-300">
        <div class="h-12 w-12 rounded-full bg-green-800 flex items-center justify-center mb-2">
          <i class="fas fa-ticket-alt text-white"></i>
        </div>
        <span class="font-bold">Basic Plan</span>
        <span class="text-sm text-gray-400">$10</span>
      </a>

      <a href="#" onclick="activatePlan('Standard', 17); document.getElementById('investModal').classList.add('hidden')" class="flex flex-col items-center justify-center bg-gray-700 hover:bg-gray-600 p-4 rounded-lg transition duration-300">
        <div class="h-12 w-12 rounded-full bg-blue-800 flex items-center justify-center mb-2">
          <i class="fas fa-ticket-alt text-white"></i>
        </div>
        <span class="font-bold">Standard Plan</span>
        <span class="text-sm text-gray-400">$17</span>
      </a>

      <a href="#" onclick="activatePlan('Premium', 35); document.getElementById('investModal').classList.add('hidden')" class="flex flex-col items-center justify-center bg-gray-700 hover:bg-gray-600 p-4 rounded-lg transition duration-300">
        <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center mb-2">
          <i class="fas fa-ticket-alt text-white"></i>
        </div>
        <span class="font-bold">Premium Plan</span>
        <span class="text-sm text-gray-400">$35</span>
      </a>

      <a href="#" onclick="activatePlan('Professional', 71); document.getElementById('investModal').classList.add('hidden')" class="flex flex-col items-center justify-center bg-gray-700 hover:bg-gray-600 p-4 rounded-lg transition duration-300">
        <div class="h-12 w-12 rounded-full bg-purple-800 flex items-center justify-center mb-2">
          <i class="fas fa-ticket-alt text-white"></i>
        </div>
        <span class="font-bold">Professional</span>
        <span class="text-sm text-gray-400">$71</span>
      </a>
    </div>

    <a href="#plans" onclick="document.getElementById('investModal').classList.add('hidden')" class="block w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black text-center py-3 rounded-lg font-bold transition duration-300">
      View All Plans
    </a>
  </div>
</div>

<!-- Bottom More Menu (slide-up panel) -->
<div id="bottomMoreMenu" class="fixed inset-x-0 bottom-0 bg-gray-900 z-50 transform translate-y-full transition-transform duration-300 ease-in-out rounded-t-2xl border-t border-gray-800">
  <div class="px-4 py-3 border-b border-gray-800">
    <div class="flex justify-between items-center">
      <h3 class="text-lg font-bold">More Options</h3>
      <button id="close-more-menu" class="rounded-full p-2 bg-gray-800 text-gray-400 hover:text-white">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>

  <div class="h-[calc(100vh-60vh)] overflow-y-auto py-4">
    <div class="grid grid-cols-3 gap-4 px-4 mb-4">
      <a href="profile.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-user-circle text-2xl mb-2 text-yellow-500"></i>
        <span class="text-xs text-center">Profile</span>
      </a>

      <a href="payment-methods.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-credit-card text-2xl mb-2 text-blue-500"></i>
        <span class="text-xs text-center">Payment</span>
      </a>

      <a href="withdraw.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-arrow-up text-2xl mb-2 text-red-500"></i>
        <span class="text-xs text-center">Withdraw</span>
      </a>

      <a href="leaderboard.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-trophy text-2xl mb-2 text-yellow-500"></i>
        <span class="text-xs text-center">Leaderboard</span>
      </a>

      <a href="deposit-history.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-history text-2xl mb-2 text-green-500"></i>
        <span class="text-xs text-center">History</span>
      </a>

      

      <a href="staking.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-landmark text-2xl mb-2 text-blue-500"></i>
        <span class="text-xs text-center">Staking</span>
      </a>

      <a href="daily-checkin.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-calendar-check text-2xl mb-2 text-green-500"></i>
        <span class="text-xs text-center">Check-in</span>
      </a>

      <a href="support.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-headset text-2xl mb-2 text-purple-500"></i>
        <span class="text-xs text-center">Support</span>
      </a>
      
      <!-- Added Admin Chat Link -->
      <a href="top_message.php" class="flex flex-col items-center justify-center bg-gray-800 p-4 rounded-lg hover:bg-gray-700 transition duration-300">
        <i class="fas fa-comment-alt text-2xl mb-2 text-indigo-500"></i>
        <span class="text-xs text-center">Admin Chat</span>
      </a>
    </div>

    <div class="mt-4 px-4">
      <a href="settings.php" class="flex items-center py-3 px-4 hover:bg-gray-800 rounded-lg transition duration-300">
        <i class="fas fa-cog text-gray-400 w-8"></i>
        <span>Settings</span>
      </a>

      <a href="backup.php" class="flex items-center py-3 px-4 hover:bg-gray-800 rounded-lg transition duration-300">
        <i class="fas fa-lock text-gray-400 w-8"></i>
        <span>Backup Codes</span>
      </a>

      <a href="../logout.php" class="flex items-center py-3 px-4 text-red-500 hover:bg-gray-800 rounded-lg transition duration-300 mt-2">
        <i class="fas fa-sign-out-alt w-8"></i>
        <span>Sign Out</span>
      </a>
    </div>
  </div>
</div>

<!-- Additional CSS for gold gradient and other styles -->
<style>
  .gold-gradient {
    background: linear-gradient(45deg, #f59e0b, #f59e0b);
  }

  .button-shine {
    position: relative;
    overflow: hidden;
  }

  .button-shine::after {
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
    animation: shine 3s infinite;
  }

  @keyframes shine {
    0% {
      transform: translateX(-100%) rotate(30deg);
    }

    20%,
    100% {
      transform: translateX(100%) rotate(30deg);
    }
  }

  /* Fix for bottom padding to account for the navigation bar */
  body {
    padding-bottom: 4rem;
  }

  @media (min-width: 768px) {
    body {
      padding-bottom: 0;
    }
  }
</style>

<!-- JavaScript for Bottom Menu -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const bottomMoreBtn = document.getElementById('bottom-more-btn');
    const bottomMoreMenu = document.getElementById('bottomMoreMenu');
    const closeMoreMenu = document.getElementById('close-more-menu');

    // Toggle bottom more menu
    bottomMoreBtn.addEventListener('click', function() {
      bottomMoreMenu.classList.toggle('translate-y-full');
      if (!bottomMoreMenu.classList.contains('translate-y-full')) {
        document.body.style.overflow = 'hidden'; // Prevent scrolling
      }
    });

    // Close more menu
    closeMoreMenu.addEventListener('click', function() {
      bottomMoreMenu.classList.add('translate-y-full');
      document.body.style.overflow = ''; // Re-enable scrolling
    });

    // Close the menu when clicking outside (on the backdrop)
    bottomMoreMenu.addEventListener('click', function(event) {
      if (event.target === bottomMoreMenu) {
        bottomMoreMenu.classList.add('translate-y-full');
        document.body.style.overflow = '';
      }
    });
  });
</script>