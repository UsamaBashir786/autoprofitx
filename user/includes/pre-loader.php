<!-- Preloader -->
<style>
  /* Base preloader styles */
  .preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: #000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  }
  
  /* Preloader Logo */
  .preloader-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 30px;
    position: relative;
  }
  
  .logo-icon {
    font-size: 40px;
    color: #f59e0b;
    margin-right: 15px;
    filter: drop-shadow(0 0 8px rgba(245, 158, 11, 0.5));
    animation: pulse 2s infinite;
  }
  
  .logo-text {
    font-size: 36px;
    font-weight: bold;
    background: linear-gradient(135deg, #f7c52b, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: 1px;
  }
  
  /* Spinner */
  .spinner-container {
    position: relative;
    width: 120px;
    height: 120px;
  }
  
  .spinner-outer {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 4px solid transparent;
    border-top-color: #f59e0b;
    animation: spin 2s linear infinite;
  }
  
  .spinner-middle {
    position: absolute;
    top: 15px;
    left: 15px;
    right: 15px;
    bottom: 15px;
    border-radius: 50%;
    border: 4px solid transparent;
    border-top-color: #f7c52b;
    animation: spin 1.5s linear infinite reverse;
  }
  
  .spinner-inner {
    position: absolute;
    top: 30px;
    left: 30px;
    right: 30px;
    bottom: 30px;
    border-radius: 50%;
    border: 4px solid transparent;
    border-top-color: #f59e0b;
    animation: spin 1s linear infinite;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .spinner-inner i {
    font-size: 24px;
    color: #f59e0b;
    animation: pulse 2s infinite;
  }
  
  /* Loading text */
  .loading-text {
    margin-top: 30px;
    font-size: 16px;
    color: #777;
    letter-spacing: 3px;
    animation: fadeInOut 1.5s infinite;
  }
  
  .loading-progress {
    margin-top: 15px;
    width: 200px;
    height: 3px;
    background-color: #222;
    border-radius: 10px;
    overflow: hidden;
  }
  
  .progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(to right, #f59e0b, #f7c52b);
    border-radius: 10px;
    animation: progress 3s ease-in-out forwards;
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
  }
  
  /* Particles */
  .particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: -1;
  }
  
  .particle {
    position: absolute;
    width: 2px;
    height: 2px;
    background-color: rgba(247, 197, 43, 0.3);
    border-radius: 50%;
  }
  
  /* Animations */
  @keyframes spin {
    0% {
      transform: rotate(0deg);
    }
    100% {
      transform: rotate(360deg);
    }
  }
  
  @keyframes pulse {
    0% {
      filter: drop-shadow(0 0 3px rgba(245, 158, 11, 0.5));
    }
    50% {
      filter: drop-shadow(0 0 10px rgba(245, 158, 11, 0.8));
    }
    100% {
      filter: drop-shadow(0 0 3px rgba(245, 158, 11, 0.5));
    }
  }
  
  @keyframes fadeInOut {
    0%, 100% {
      opacity: 0.3;
    }
    50% {
      opacity: 1;
    }
  }
  
  @keyframes progress {
    0% {
      width: 0%;
    }
    50% {
      width: 70%;
    }
    100% {
      width: 100%;
    }
  }
  
  /* Preloader exit animation */
  .preloader.hidden {
    animation: fadeOut 0.5s forwards;
  }
  
  @keyframes fadeOut {
    from {
      opacity: 1;
    }
    to {
      opacity: 0;
      visibility: hidden;
    }
  }
</style>

<div class="preloader" id="preloader">
  <!-- Particles Background -->
  <div class="particles" id="particles"></div>
  
  <!-- Logo -->
  <div class="preloader-logo">
    <i class="fas fa-gem logo-icon"></i>
    <span class="logo-text">AutoProftX</span>
  </div>
  
  <!-- Spinner -->
  <div class="spinner-container">
    <div class="spinner-outer"></div>
    <div class="spinner-middle"></div>
    <div class="spinner-inner">
      <i class="fas fa-chart-line"></i>
    </div>
  </div>
  
  <!-- Loading Text & Progress -->
  <div class="loading-text">LOADING</div>
  <div class="loading-progress">
    <div class="progress-bar" id="progress-bar"></div>
  </div>
</div>

<script>
  // Create particles
  document.addEventListener('DOMContentLoaded', function() {
    // Create particles
    const particlesContainer = document.getElementById('particles');
    const particleCount = 50;
    
    for (let i = 0; i < particleCount; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      
      // Random position
      const posX = Math.random() * 100;
      const posY = Math.random() * 100;
      
      // Random size
      const size = Math.random() * 3 + 1;
      
      // Random opacity
      const opacity = Math.random() * 0.5 + 0.1;
      
      // Set styles
      particle.style.left = posX + '%';
      particle.style.top = posY + '%';
      particle.style.width = size + 'px';
      particle.style.height = size + 'px';
      particle.style.opacity = opacity;
      
      // Add animation
      particle.style.animation = `pulse ${Math.random() * 3 + 2}s infinite`;
      
      // Append to container
      particlesContainer.appendChild(particle);
    }
    
    // Add special class for preloading elements
    const goldGradientElements = document.getElementsByClassName('gold-gradient');
    const cardHoverElements = document.getElementsByClassName('card-hover');
    
    // Show main content and hide preloader once everything is loaded
    window.addEventListener('load', function() {
      setTimeout(function() {
        document.getElementById('main-content').style.display = 'block';
        const preloader = document.getElementById('preloader');
        preloader.classList.add('hidden');
        
        // After animation completes, actually remove the preloader from the DOM
        setTimeout(function() {
          preloader.style.display = 'none';
        }, 500);
      }, 500); // Wait for 2 seconds to simulate loading
    });
  });
</script>