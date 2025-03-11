<!-- Navigation -->
<nav class="bg-black border-b border-gray-800 sticky top-0 z-50">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16">
      <!-- Mobile menu button -->
      <div class="md:hidden flex items-center">
        <button id="mobile-menu-button" class="text-gray-300 hover:text-white p-2 rounded-md focus:outline-none">
          <span class="sr-only">Open main menu</span>
          <i class="fas fa-bars text-xl"></i>
        </button>
      </div>
      <!-- Logo -->
      <div class="flex items-center">
        <div class="flex-shrink-0 flex items-center">
          <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
          <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">AutoProftX</span>
        </div>
      </div>

      <!-- Desktop Navigation Links -->
      <div class="hidden md:flex items-center space-x-1 lg:space-x-4">
        <a href="index.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">
          <i class="fas fa-chart-line mr-1 hidden lg:inline"></i>Dashboard
        </a>
        <a href="payment-methods.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">
          <i class="fas fa-credit-card mr-1 hidden lg:inline"></i>Payment
        </a>
        <a href="referrals.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">
          <i class="fas fa-users mr-1 hidden lg:inline"></i>Referrals
        </a>
        <a href="deposit.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">
          <i class="fas fa-money-bill mr-1 hidden lg:inline"></i>Add Funds
        </a>
        <a href="leaderboard.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300 hidden lg:block">
          <i class="fas fa-trophy mr-1"></i>Leaderboard
        </a>
        <a href="support.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300 hidden lg:block">
          <i class="fas fa-headset mr-1"></i>Support
        </a>
        <a href="backup.php" class="px-2 lg:px-3 py-2 text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300 hidden lg:block">
          <i class="fas fa-lock mr-1"></i>Back Up Codes
        </a>

        <!-- User Menu Dropdown -->
        <div class="ml-1 lg:ml-3">
          <div>
            <button type="button" class="flex items-center text-sm rounded-full focus:outline-none" id="user-menu-button" aria-expanded="false">
              <span class="sr-only">Open user menu</span>
              <div class="h-8 w-8 rounded-full bg-gray-800 flex items-center justify-center border border-yellow-500 text-yellow-500">
                <i class="fas fa-user"></i>
              </div>
              <span class="ml-2 text-gray-300 hidden lg:inline"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?></span>
              <i class="fas fa-chevron-down ml-1 text-xs text-gray-500"></i>
            </button>
          </div>

          <!-- Dropdown menu -->
          <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none z-50" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-yellow-500" role="menuitem">
              <i class="fas fa-user-circle mr-2"></i>Your Profile
            </a>
            <a href="deposit-history.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-yellow-500" role="menuitem">
              <i class="fas fa-history mr-2"></i>Transaction History
            </a>
            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-yellow-500" role="menuitem">
              <i class="fas fa-cog mr-2"></i>Settings
            </a>
            <div class="border-t border-gray-700 my-1"></div>
            <a href="../logout.php" class="block px-4 py-2 text-sm text-red-500 hover:bg-gray-700" role="menuitem">
              <i class="fas fa-sign-out-alt mr-2"></i>Sign out
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Full Screen Mobile Menu -->
<div id="mobile-menu" class="fixed inset-0 bg-black bg-opacity-95 z-50 transform -translate-y-full transition-transform duration-300 ease-in-out md:hidden">
  <div class="flex flex-col h-full justify-start items-center relative pt-16 pb-8 overflow-y-auto">

    <!-- User profile section -->
    <div class="flex flex-col items-center mb-8 pt-4">
      <div class="h-20 w-20 rounded-full bg-gray-800 flex items-center justify-center border-2 border-yellow-500 text-yellow-500 mb-3">
        <i class="fas fa-user text-3xl"></i>
      </div>
      <p class="text-xl font-bold text-white"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?></p>
    </div>

    <!-- Mobile Nav Links -->
    <div class="flex flex-col space-y-4 items-center w-full px-8">
      <a href="index.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-chart-line mr-3"></i>Dashboard
      </a>
      <a href="payment-methods.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-credit-card mr-3"></i>Payment Methods
      </a>
      <a href="referrals.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-users mr-3"></i>Referrals
      </a>
      <a href="deposit.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-money-bill mr-3"></i>Add Funds
      </a>
      <a href="leaderboard.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-trophy mr-3"></i>Leaderboard
      </a>
      <a href="deposit-history.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-history mr-3"></i>Transaction History
      </a>
      <a href="profile.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-user-circle mr-3"></i>Profile
      </a>
      <a href="support.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-headset mr-3"></i>Support
      </a>
      <a href="settings.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-cog mr-3"></i>Settings
      </a>
      <a href="backup.php" class="w-full text-center py-3 text-lg font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link border-b border-gray-800">
        <i class="fas fa-lock mr-3"></i>Back Up Codes
      </a>
      <a href="logout.php" class="w-full text-center py-4 text-lg font-medium text-red-500 hover:text-red-400 transition duration-300 mobile-menu-link mt-4">
        <i class="fas fa-sign-out-alt mr-3"></i>Sign Out
      </a>
    </div>
    <!-- Close button -->
    <button id="close-menu-button" class="absolute top-4 right-4 text-gray-300 hover:text-white text-2xl p-2">
      <i class="fas fa-times"></i>
    </button>
  </div>
</div>

<!-- JavaScript for toggles -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // User dropdown toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');

    if (userMenuButton && userDropdown) {
      userMenuButton.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdown.classList.toggle('hidden');
        userMenuButton.setAttribute('aria-expanded', userDropdown.classList.contains('hidden') ? 'false' : 'true');
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function(event) {
        if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
          userDropdown.classList.add('hidden');
          userMenuButton.setAttribute('aria-expanded', 'false');
        }
      });
    }

    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const closeMenuButton = document.getElementById('close-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileMenuLinks = document.querySelectorAll('.mobile-menu-link');

    if (mobileMenuButton && mobileMenu) {
      mobileMenuButton.addEventListener('click', function() {
        mobileMenu.classList.remove('-translate-y-full');
        document.body.classList.add('overflow-hidden'); // Prevent scrolling when menu is open
      });

      closeMenuButton.addEventListener('click', function() {
        mobileMenu.classList.add('-translate-y-full');
        document.body.classList.remove('overflow-hidden');
      });

      mobileMenuLinks.forEach(link => {
        link.addEventListener('click', function() {
          mobileMenu.classList.add('-translate-y-full');
          document.body.classList.remove('overflow-hidden');
        });
      });
    }

    // Add resize listener to handle mobile to desktop transitions
    window.addEventListener('resize', function() {
      if (window.innerWidth >= 768) { // md breakpoint
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenu && !mobileMenu.classList.contains('-translate-y-full')) {
          mobileMenu.classList.add('-translate-y-full');
          document.body.classList.remove('overflow-hidden');
        }
      }
    });
  });
</script>
<style>
  /* Enhanced Dropdown Styling */
  #user-dropdown {
    width: 250px;
    /* Increased width */
    right: 40px;
    /* Adjust position */
    top: 120%;
    /* Position below the button with some space */
    padding: 12px 0;
    /* More vertical padding */
    border-radius: 8px;
    background-color: #1f2937;
    /* Slightly lighter than black for contrast */
    border: 1px solid #374151;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.25);
    transform-origin: top right;
    transition: transform 0.2s ease-out, opacity 0.2s ease-out;
  }

  /* Add animation for dropdown */
  #user-dropdown.hidden {
    opacity: 0;
    transform: scale(0.95);
    pointer-events: none;
    /* Ensure it's not clickable when hidden */
  }

  #user-dropdown:not(.hidden) {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
  }

  /* Triangle indicator on dropdown */
  #user-dropdown::before {
    content: '';
    position: absolute;
    top: -8px;
    right: 28px;
    width: 16px;
    height: 16px;
    background-color: #1f2937;
    transform: rotate(45deg);
    border-left: 1px solid #374151;
    border-top: 1px solid #374151;
    z-index: -1;
  }

  /* Menu items styling */
  #user-dropdown a {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.2s ease;
  }

  #user-dropdown a:hover {
    background-color: #2d3748;
    padding-left: 20px;
    /* Slightly shift text on hover */
  }

  /* Icons in dropdown */
  #user-dropdown a i {
    width: 20px;
    text-align: center;
    margin-right: 12px;
    font-size: 16px;
  }

  /* Divider styling */
  #user-dropdown .border-t {
    margin: 8px 0;
    border-color: #374151;
  }

  /* Sign out button special styling */
  #user-dropdown a:last-child {
    margin-top: 4px;
    margin-bottom: 4px;
    color: #ef4444;
    /* Red color */
    font-weight: 500;
  }

  #user-dropdown a:last-child:hover {
    background-color: rgba(239, 68, 68, 0.1);
    /* Light red background on hover */
  }

  /* User menu button styling */
  #user-menu-button {
    position: relative;
    padding: 6px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
  }

  #user-menu-button:hover {
    background-color: rgba(255, 255, 255, 0.05);
  }

  /* When dropdown is open, highlight the button */
  #user-menu-button[aria-expanded="true"] {
    background-color: rgba(255, 255, 255, 0.1);
  }

  /* Adjust chevron icon when dropdown is open */
  #user-menu-button[aria-expanded="true"] i.fa-chevron-down {
    transform: rotate(180deg);
    color: #eab308;
    /* Yellow color when open */
  }

  /* Transition for chevron */
  #user-menu-button i.fa-chevron-down {
    transition: transform 0.2s ease;
  }

  /* User avatar styling */
  #user-menu-button .rounded-full {
    transition: all 0.2s ease;
  }

  #user-menu-button:hover .rounded-full {
    border-color: #eab308;
    box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.2);
  }
</style>