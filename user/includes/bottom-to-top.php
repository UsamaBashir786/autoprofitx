  <!-- Scroll to top button -->
  <div id="scrollToTopBtn" class="scroll-to-top">
    <i class="fas fa-arrow-up"></i>
  </div>
  <script>
    // Scroll to top functionality
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');

    // Show button when page is scrolled down
    window.addEventListener('scroll', () => {
      if (window.pageYOffset > 300) {
        scrollToTopBtn.classList.add('visible');
      } else {
        scrollToTopBtn.classList.remove('visible');
      }
    });

    // Smooth scroll to top when button is clicked
    scrollToTopBtn.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  </script>
  <style>
    @media screen and (max-width:768px){
      .scroll-to-top{
        display: none !important;
      }
    }
    .scroll-to-top {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 50px;
      height: 50px;
      background: linear-gradient(to bottom, #f59e0b, #d97706);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #000;
      font-size: 20px;
      cursor: pointer;
      transition: all 0.3s;
      opacity: 0;
      visibility: hidden;
      transform: translateY(20px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      z-index: 999;
    }

    .scroll-to-top.visible {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .scroll-to-top:hover {
      background: linear-gradient(to bottom, #fbbf24, #f59e0b);
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    }

    .scroll-to-top:active {
      transform: translateY(-2px);
    }

    .scroll-to-top i {
      transition: transform 0.3s;
    }

    .scroll-to-top:hover i {
      transform: translateY(-3px);
    }
  </style>