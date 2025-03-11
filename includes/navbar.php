  <!-- Luxury Navigation -->
  <nav class="bg-black border-b border-gray-800 sticky top-0 z-50">
    <div class="container mx-auto px-6 py-4">
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
          <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">AutoProftX</span>
        </div>
        <div class="hidden md:flex space-x-8 items-center">
          <a href="index.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">Home</a>
          <a href="guide.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">How It Work</a>
          <a href="register.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">Register</a>
          <a href="about.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">About Us</a>
          <button onclick="window.location.href='login.php'" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-2 px-6 rounded-md transition duration-300 shadow-lg">
            Login
          </button>
        </div>
        <div class="md:hidden">
          <button id="mobile-menu-button" class="text-gray-300 hover:text-white">
            <i class="fas fa-bars"></i>
          </button>
        </div>
        <div id="mobile-menu" class="fixed inset-0 bg-black bg-opacity-90 z-50 transform -translate-y-full transition-transform duration-300 ease-in-out">
          <div class="flex flex-col h-full justify-center items-center relative">
            <button id="close-menu-button" class="absolute top-6 right-6 text-gray-300 hover:text-white text-2xl">
              <i class="fas fa-times"></i>
            </button>
            <div class="flex flex-col space-y-6 items-center">
              <a href="index.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">Home</a>
              <a href="guide.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">How It Works</a>
              <a href="register.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">Register</a>
              <a href="about.php" class="text-sm font-medium text-gray-300 hover:text-yellow-500 transition duration-300">About Us</a>
              <button onclick="window.location.href='login.php'" class="mt-4 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-8 rounded-md transition duration-300 shadow-lg">
                Login
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>
  <!-- Luxury Mobile Bottom Navigation Bar -->
  <div class="md:hidden fixed bottom-0 left-0 right-0 bg-black border-t border-gray-800 z-40 shadow-lg">
    <div class="grid grid-cols-5 h-16">
      <!-- Home -->
      <a href="index.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
        <i class="fas fa-home text-lg"></i>
        <span class="text-xs mt-1">Home</span>
      </a>

      <!-- How It Works -->
      <a href="guide.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
        <i class="fas fa-info-circle text-lg"></i>
        <span class="text-xs mt-1">Guide</span>
      </a>

      <!-- Login Button (Center, Highlighted) -->
      <a href="login.php" class="relative flex flex-col items-center justify-center">
        <div class="absolute -top-5 w-16 h-16 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center shadow-lg border-4 border-black">
          <i class="fas fa-sign-in-alt text-black text-xl"></i>
        </div>
        <span class="text-xs mt-8 text-yellow-500">Login</span>
      </a>

      <!-- Register -->
      <a href="register.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
        <i class="fas fa-user-plus text-lg"></i>
        <span class="text-xs mt-1">Register</span>
      </a>

      <!-- About Us -->
      <a href="about.php" class="flex flex-col items-center justify-center text-gray-300 hover:text-yellow-500 transition duration-300">
        <i class="fas fa-info text-lg"></i>
        <span class="text-xs mt-1">About</span>
      </a>
    </div>
  </div>

  <!-- Additional CSS for styles and responsive adjustments -->
  <style>
    /* Fix for bottom padding to account for the navigation bar on mobile */
    @media (max-width: 767px) {
      body {
        padding-bottom: 5rem !important;
      }

      /* Add subtle animation for bottom bar items */
      .fixed.bottom-0 a:active {
        transform: scale(0.95);
      }

      /* Glow effect for center button */
      .fixed.bottom-0 a .absolute {
        box-shadow: 0 0 15px rgba(245, 158, 11, 0.5);
      }
    }

    /* Enhance the center button with a subtle animation */
    @keyframes pulse-gold {
      0% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
      }

      70% {
        box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
      }
    }

    .fixed.bottom-0 a .absolute {
      animation: pulse-gold 2s infinite;
    }
  </style>

  <!-- JavaScript to handle active state -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Get current page URL
      const currentPath = window.location.pathname;

      // Get all bottom navigation links
      const bottomNavLinks = document.querySelectorAll('.fixed.bottom-0 a');

      // Set active state based on current URL
      bottomNavLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPath.includes(href) && href !== 'index.php') {
          link.classList.add('text-yellow-500');
          link.classList.remove('text-gray-300');
        } else if (href === 'index.php' && (currentPath === '/' || currentPath.endsWith('index.php'))) {
          link.classList.add('text-yellow-500');
          link.classList.remove('text-gray-300');
        }
      });
    });
  </script>
