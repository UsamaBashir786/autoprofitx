<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoProftX - Under Development</title>
  
  <!-- Tailwind CSS -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #111827; /* bg-gray-900 */
    }
    
    .animate-pulse {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse {
      0%, 100% {
        opacity: 1;
      }
      50% {
        opacity: .5;
      }
    }
    
    .animate-bounce {
      animation: bounce 1s infinite;
    }
    
    @keyframes bounce {
      0%, 100% {
        transform: translateY(-5%);
        animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
      }
      50% {
        transform: translateY(0);
        animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
      }
    }
    
    .glow {
      text-shadow: 0 0 5px rgba(234, 179, 8, 0.3), 0 0 10px rgba(234, 179, 8, 0.2);
    }
  </style>
</head>

<body class="bg-gray-900 text-white min-h-screen flex flex-col">
  <!-- Navbar -->
  <nav class="bg-gray-800 border-b border-gray-700">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
      <a href="index.php" class="text-xl font-bold text-yellow-500">AutoProftX</a>
      <a href="index.php" class="px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 transition duration-300">
        <i class="fas fa-home mr-2"></i>Home
      </a>
    </div>
  </nav>
  <?php include 'includes/mobile-bar.php'; ?>


  <!-- Pre-loader -->
  <div class="preloader fixed inset-0 z-50 flex items-center justify-center bg-gray-900" style="display: flex;">
    <div class="flex flex-col items-center">
      <div class="w-16 h-16 border-4 border-yellow-500 border-t-transparent rounded-full animate-spin mb-4"></div>
      <p class="text-yellow-500 text-lg">Loading...</p>
    </div>
  </div>

  <!-- Main Content -->
  <main id="main-content" style="display: none;" class="flex-grow flex items-center justify-center px-4">
    <div class="text-center max-w-3xl mx-auto py-16">
      <div class="mb-8 flex justify-center">
        <div class="relative">
          <i class="fas fa-tools text-yellow-500 text-7xl animate-pulse"></i>
          <i class="fas fa-cog absolute top-0 right-0 text-yellow-600 text-3xl animate-spin"></i>
        </div>
      </div>
      
      <h1 class="text-4xl md:text-5xl font-bold mb-4 text-yellow-500 glow">Under Development</h1>
      
      <div class="bg-gray-800 p-6 md:p-8 rounded-lg border border-gray-700 mb-8">
        <p class="text-xl md:text-2xl mb-6">We're working hard to bring you an amazing experience!</p>
        <p class="text-gray-300 mb-4">Our team is currently developing this section of the platform. We appreciate your patience as we build new features to enhance your investment journey.</p>
        <div class="flex items-center justify-center text-yellow-500 space-x-2 mt-2 animate-bounce">
          <i class="fas fa-chevron-down"></i>
          <i class="fas fa-chevron-down"></i>
          <i class="fas fa-chevron-down"></i>
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gray-800 p-5 rounded-lg border border-gray-700">
          <i class="fas fa-clock text-yellow-500 text-3xl mb-4"></i>
          <h3 class="text-xl font-semibold mb-2">Coming Soon</h3>
          <p class="text-gray-400">This feature will be available in the next update.</p>
        </div>
        
        <div class="bg-gray-800 p-5 rounded-lg border border-gray-700">
          <i class="fas fa-bell text-yellow-500 text-3xl mb-4"></i>
          <h3 class="text-xl font-semibold mb-2">Get Notified</h3>
          <p class="text-gray-400">We'll let you know as soon as this section is ready.</p>
        </div>
        
        <div class="bg-gray-800 p-5 rounded-lg border border-gray-700">
          <i class="fas fa-question-circle text-yellow-500 text-3xl mb-4"></i>
          <h3 class="text-xl font-semibold mb-2">Need Help?</h3>
          <p class="text-gray-400">Contact our support team for assistance.</p>
        </div>
      </div>
      
      <div class="mt-10">
        <a href="index.php" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-6 rounded-md transition duration-300 inline-flex items-center">
          <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-gray-800 border-t border-gray-700 py-8">
    <div class="container mx-auto px-4">
      <div class="text-center">
        <p class="text-gray-400">&copy; 2025 AutoProftX. All rights reserved.</p>
        <div class="flex justify-center space-x-4 mt-4">
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300">
            <i class="fab fa-facebook"></i>
          </a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300">
            <i class="fab fa-twitter"></i>
          </a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300">
            <i class="fab fa-linkedin"></i>
          </a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Show content once page is loaded
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1500);
    });
  </script>
</body>
</html>