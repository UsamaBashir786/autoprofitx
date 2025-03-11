<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php' ?>
  <style>
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

    .timeline-item:not(:last-child)::after {
      content: '';
      position: absolute;
      left: 17px;
      top: 30px;
      bottom: -30px;
      width: 2px;
      background: linear-gradient(to bottom, #f59e0b, rgba(245, 158, 11, 0.1));
    }
  </style>
</head>

<body class="bg-black text-white font-sans">
  <?php include 'includes/navbar.php' ?>

  <!-- About Hero Section with SVGs -->
  <div class="relative py-16 md:py-24 overflow-hidden">
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
        <path d="M50,20 L50,80 M20,50 L80,50" stroke="#f59e0b" stroke-width="2" />
        <circle cx="50" cy="50" r="15" fill="#f59e0b" opacity="0.3" />
      </svg>
    </div>

    <div class="absolute left-1/4 bottom-20 opacity-70 w-24 h-24 floating hidden md:block">
      <svg viewBox="0 0 100 100" class="pulse-gold">
        <defs>
          <linearGradient id="goldGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f7e084" />
            <stop offset="50%" stop-color="#f59e0b" />
            <stop offset="100%" stop-color="#e67e00" />
          </linearGradient>
        </defs>
        <path d="M10,50 C10,30 30,10 50,10 C70,10 90,30 90,50 C90,70 70,90 50,90 C30,90 10,70 10,50 Z" fill="none" stroke="url(#goldGradient)" stroke-width="2" />
        <path d="M30,30 L70,70 M30,70 L70,30" stroke="#f59e0b" stroke-width="2" />
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
          <span class="block">About</span>
          <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">AutoProftX</span>
        </h1>

        <p class="text-lg text-gray-300 mb-8 max-w-xl mx-auto fade-in" style="animation-delay: 0.4s;">
          Pioneering the future of automated investment solutions with cutting-edge technology and financial expertise
        </p>

        <!-- SVG design element under paragraph -->
        <div class="mb-8 flex justify-center">
          <svg width="300" height="60" viewBox="0 0 300 60">
            <rect x="0" y="0" width="300" height="60" fill="transparent" />
            <path d="M50,30 L120,30 M180,30 L250,30" stroke="#f59e0b" stroke-width="1" stroke-dasharray="5,5" />
            <circle cx="150" cy="30" r="20" fill="none" stroke="#f59e0b" stroke-width="1.5" />
            <circle cx="150" cy="30" r="10" fill="#f59e0b" opacity="0.2" />
            <circle cx="150" cy="30" r="5" fill="#f59e0b" />
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- Our Story Section -->
  <div class="py-16 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <div class="slide-up" style="animation-delay: 0.3s;">
          <h2 class="text-3xl font-bold mb-6">Our <span class="text-yellow-500">Story</span></h2>
          <p class="text-gray-300 mb-6">
            Founded in 2023, AutoProftX emerged from a vision to democratize access to sophisticated investment algorithms previously reserved for institutional investors. Our founders, with over 25 years of combined experience in financial markets and technology, recognized the need for reliable, transparent, and accessible automated profit solutions.
          </p>
          <p class="text-gray-300 mb-6">
            We began with a simple mission: create an investment platform that delivers consistent returns while minimizing risk. Our proprietary trading algorithms were developed over three years of rigorous testing and optimization, resulting in our signature 20% return model.
          </p>
          <p class="text-gray-300">
            Today, AutoProftX serves thousands of investors worldwide, from beginners making their first investment to seasoned professionals seeking to diversify their portfolios with reliable automated returns.
          </p>
        </div>

        <div class="relative scale-in" style="animation-delay: 0.5s;">
          <!-- Timeline -->
          <div class="bg-gradient-to-b from-gray-800 to-gray-900 p-8 rounded-xl shadow-xl border border-gray-700">
            <h3 class="text-2xl font-bold mb-6 text-yellow-500">Company Timeline</h3>

            <div class="space-y-10">
              <div class="timeline-item relative pl-10">
                <div class="absolute left-0 top-0 w-7 h-7 rounded-full bg-gray-800 border-2 border-yellow-500 flex items-center justify-center">
                  <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                </div>
                <h4 class="text-lg font-bold">2023</h4>
                <p class="text-gray-400">AutoProftX founded with seed investment of $2.5M</p>
              </div>

              <div class="timeline-item relative pl-10">
                <div class="absolute left-0 top-0 w-7 h-7 rounded-full bg-gray-800 border-2 border-yellow-500 flex items-center justify-center">
                  <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                </div>
                <h4 class="text-lg font-bold">2023</h4>
                <p class="text-gray-400">Launch of Beta platform with 500 early adopters</p>
              </div>

              <div class="timeline-item relative pl-10">
                <div class="absolute left-0 top-0 w-7 h-7 rounded-full bg-gray-800 border-2 border-yellow-500 flex items-center justify-center">
                  <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                </div>
                <h4 class="text-lg font-bold">2024</h4>
                <p class="text-gray-400">Official platform launch with Premium and VIP tiers</p>
              </div>

              <div class="timeline-item relative pl-10">
                <div class="absolute left-0 top-0 w-7 h-7 rounded-full bg-gray-800 border-2 border-yellow-500 flex items-center justify-center">
                  <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                </div>
                <h4 class="text-lg font-bold">2025</h4>
                <p class="text-gray-400">Expansion to global markets with multi-currency support</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Our Mission & Vision -->
  <div class="py-16 bg-black">
    <div class="container mx-auto px-6">
      <div class="text-center mb-12 slide-up" style="animation-delay: 0.2s;">
        <h2 class="text-3xl font-bold text-white mb-4">Our Mission & Vision</h2>
        <p class="text-gray-400 max-w-2xl mx-auto">
          Guiding principles that drive our innovation and commitment to our investors
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
        <!-- Mission -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group p-8 slide-up" style="animation-delay: 0.4s;">
          <div class="flex items-center justify-center h-16 w-16 rounded-md bg-gray-800 border border-yellow-500 text-yellow-500 mb-6 mx-auto">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>

          <h3 class="text-xl font-bold text-white mb-4 text-center">Our Mission</h3>

          <p class="text-gray-300 mb-6 text-center">
            To democratize access to sophisticated financial algorithms that generate consistent returns, empowering investors of all levels to achieve their financial goals with confidence and security.
          </p>

          <ul class="space-y-4">
            <li class="flex items-start">
              <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
              <span class="text-gray-300">Provide accessible investment solutions for all</span>
            </li>
            <li class="flex items-start">
              <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
              <span class="text-gray-300">Maintain complete transparency in all operations</span>
            </li>
            <li class="flex items-start">
              <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
              <span class="text-gray-300">Deliver consistent and reliable returns</span>
            </li>
          </ul>
        </div>

        <!-- Vision -->
        <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700 transition-all duration-300 hover:border-yellow-500 group p-8 slide-up" style="animation-delay: 0.6s;">
          <div class="flex items-center justify-center h-16 w-16 rounded-md bg-gray-800 border border-yellow-500 text-yellow-500 mb-6 mx-auto">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </div>

          <h3 class="text-xl font-bold text-white mb-4 text-center">Our Vision</h3>

          <p class="text-gray-300 mb-6 text-center">
            To become the global leader in automated investment solutions, recognized for our innovation, reliability, and commitment to creating financial freedom for our clients through technology-driven strategies.
          </p>

          <ul class="space-y-4">
            <li class="flex items-start">
              <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
              <span class="text-gray-300">Pioneer new algorithmic trading technologies</span>
            </li>
            <li class="flex items-start">
              <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
              <span class="text-gray-300">Expand our services to diverse global markets</span>
            </li>
            <li class="flex items-start">
              <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
              <span class="text-gray-300">Build a community of financially empowered investors</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</body>

</html>