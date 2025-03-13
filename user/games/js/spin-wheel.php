<script>
  // Better preloader handling
  document.addEventListener('DOMContentLoaded', function() {
    // Hide both preloaders and show content
    setTimeout(function() {
      // First hide the standard preloader if it exists
      const standardPreloader = document.querySelector('.preloader');
      if (standardPreloader) {
        standardPreloader.style.display = 'none';
      }

      // Then fade out the wheel preloader
      const wheelPreloader = document.getElementById('wheel-preloader');
      if (wheelPreloader) {
        wheelPreloader.style.opacity = '0';
      }

      // After fade animation completes, hide preloader and show content
      setTimeout(function() {
        if (wheelPreloader) {
          wheelPreloader.style.display = 'none';
        }

        const mainContent = document.getElementById('main-content');
        if (mainContent) {
          mainContent.style.display = 'block';
        }
      }, 500);
    }, 2000);
  });
</script>
<?php include '../includes/js-links.php'; ?>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Hide preloader
    setTimeout(function() {
      document.querySelector('.preloader').style.display = 'none';
      document.getElementById('main-content').style.display = 'block';
    }, 1500);

    // Wheel segments
    const segments = [{
        value: 0,
        label: 'LOSE',
        color: '#e53e3e'
      },
      {
        value: 0.5,
        label: '0.5x',
        color: '#d69e2e'
      },
      {
        value: 1,
        label: '1x',
        color: '#38a169'
      },
      {
        value: 1.5,
        label: '1.5x',
        color: '#3182ce'
      },
      {
        value: 2,
        label: '2x',
        color: '#805ad5'
      },
      {
        value: 3,
        label: '3x',
        color: '#dd6b20'
      },
      {
        value: 5,
        label: '5x',
        color: '#0d9488'
      },
      {
        value: 10,
        label: '10x',
        color: '#6366f1'
      },
      {
        value: 20,
        label: '20x',
        color: '#7e22ce'
      },
      {
        value: 50,
        label: '50x',
        color: '#be123c'
      },
      {
        value: 100,
        label: '100x',
        color: '#fbbf24'
      }
    ];

    const wheel = document.getElementById('wheel');
    const manualSpinBtn = document.getElementById('manual-spin');
    const submitSpinBtn = document.getElementById('submit-spin');
    const betAmountInput = document.getElementById('bet_amount');
    const betHalfBtn = document.getElementById('bet-half');
    const presetBtns = document.querySelectorAll('.preset-bet');
    const spinForm = document.getElementById('spin-form');

    // Add segments to wheel
    segments.forEach((segment, index) => {
      const angle = (index * 360 / segments.length);
      const textElem = document.createElement('div');
      textElem.className = 'segment';
      textElem.textContent = segment.label;
      textElem.style.transform = `rotate(${angle}deg)`;
      wheel.appendChild(textElem);
    });

    // Handle preset bet buttons
    presetBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const amount = this.getAttribute('data-amount');
        betAmountInput.value = amount;
      });
    });

    // Handle half bet button
    betHalfBtn.addEventListener('click', function() {
      const currentVal = parseFloat(betAmountInput.value) || 0;
      betAmountInput.value = Math.max(1, currentVal / 2).toFixed(2);
    });

    // Manual spin button triggers form submission
    manualSpinBtn.addEventListener('click', function() {
      submitSpinBtn.click();
    });

    <?php if (!empty($result_message)): ?>
      // Spin animation if we have a result
      let resultAngle = 0;

      // Calculate result angle based on segment
      const segmentIndex = <?php echo $selected_index ?? 0; ?>;
      const segmentAngle = 360 / segments.length;
      resultAngle = 3600 + (segmentIndex * segmentAngle) + (segmentAngle / 2);

      // Spin the wheel
      wheel.style.transform = `rotate(-${resultAngle}deg)`;

      <?php if ($result_type === 'win' && $multiplier >= 5): ?>
        // Create confetti effect for big wins
        for (let i = 0; i < 100; i++) {
          createConfetti();
        }
      <?php endif; ?>
    <?php endif; ?>

    // Function to create a confetti particle
    function createConfetti() {
      const confetti = document.createElement('div');
      confetti.className = 'confetti';

      // Random position
      confetti.style.left = Math.random() * 100 + 'vw';
      confetti.style.top = -20 + 'px';

      // Random color
      const colors = ['#f6e05e', '#ed8936', '#38a169', '#3182ce', '#805ad5', '#d53f8c'];
      confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];

      // Random shape
      const shapes = ['circle', 'square', 'triangle'];
      const shape = shapes[Math.floor(Math.random() * shapes.length)];

      if (shape === 'circle') {
        confetti.style.borderRadius = '50%';
      } else if (shape === 'triangle') {
        confetti.style.width = '0';
        confetti.style.height = '0';
        confetti.style.borderLeft = '5px solid transparent';
        confetti.style.borderRight = '5px solid transparent';
        confetti.style.borderBottom = '10px solid ' + confetti.style.backgroundColor;
        confetti.style.backgroundColor = 'transparent';
      }

      // Random size
      const size = Math.random() * 10 + 5;
      confetti.style.width = size + 'px';
      confetti.style.height = size + 'px';

      // Random animation duration
      confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
      // Append to body
      document.body.appendChild(confetti);
      // Remove after animation
      setTimeout(() => {
        confetti.remove();
      }, 5000);
    }
  });
</script>