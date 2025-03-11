<?php
// Start session
session_start();

// Database connection
include '../config/db.php';
include 'includes/referral-functions.php';  // Include the referral functions file
include 'includes/generate-referral-code.php';  // Include the code generator

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Get user's referral code
$ref_code_query = "SELECT referral_code FROM users WHERE id = ?";
$stmt = $conn->prepare($ref_code_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$referral_code = "";
if ($result->num_rows > 0) {
  $user_data = $result->fetch_assoc();
  $referral_code = $user_data['referral_code'];
}
$stmt->close();

// If referral code is empty, generate one and update the user record
if (empty($referral_code)) {
  $referral_code = assignReferralCodeToUser($conn, $user_id);

  if (!$referral_code) {
    // If error generating code, show an error message
    $error_message = "There was an error generating your referral code. Please try again later.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .copy-animation {
      animation: pulse 0.5s;
    }

    @keyframes pulse {
      0% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }

      100% {
        transform: scale(1);
      }
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>

  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main id="main-content" class="flex-grow py-6 bg-gray-900">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-5xl">
      <!-- Page Header with Enhanced Styling -->
      <div class="mb-8 text-center md:text-left">
        <div class="inline-block bg-yellow-500 bg-opacity-20 px-4 py-1 rounded-full mb-2">
          <span class="text-yellow-400 text-sm font-medium flex items-center">
            <i class="fas fa-gift mr-2"></i> Rewards Program
          </span>
        </div>
        <h1 class="text-3xl md:text-4xl font-bold">Refer & Earn</h1>
        <p class="text-gray-400 mt-2 max-w-2xl">Invite friends and earn $5 for each successful referral!</p>
      </div>

      <?php if (isset($error_message)): ?>
        <div class="bg-red-800 text-red-100 p-4 rounded-lg mb-6 flex items-center">
          <i class="fas fa-exclamation-circle mr-2"></i>
          <?php echo $error_message; ?>
          <button class="ml-auto text-red-200 hover:text-white" onclick="this.parentElement.style.display='none';">
            <i class="fas fa-times"></i>
          </button>
        </div>
      <?php endif; ?>

      <!-- Animated Earnings Counter -->
      <div class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-xl border border-gray-700 shadow-lg p-6 mb-8">
        <div class="flex flex-col md:flex-row items-center md:items-start justify-between gap-4 md:gap-8">
          <div class="text-center md:text-left">
            <h2 class="text-xl font-bold mb-4">Your Earnings</h2>
            <div class="text-4xl font-bold text-yellow-500">
              $<span id="earnings-counter">0</span>
            </div>
            <p class="text-gray-400 mt-2">Total earned from referrals</p>
          </div>

          <div class="flex-1 bg-gray-800 bg-opacity-50 rounded-lg p-4 border border-gray-700">
            <div class="flex items-center justify-between mb-3">
              <span class="text-gray-300 font-medium">This week</span>
              <span class="text-yellow-500 font-bold">$0</span>
            </div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-gray-300 font-medium">This month</span>
              <span class="text-yellow-500 font-bold">$0</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-300 font-medium">All time</span>
              <span class="text-yellow-500 font-bold">$0</span>
            </div>
          </div>

          <div class="flex-1 flex flex-col items-center bg-gray-800 bg-opacity-50 rounded-lg p-4 border border-gray-700">
            <div class="h-20 w-20 rounded-full bg-yellow-500 bg-opacity-20 flex items-center justify-center mb-3">
              <i class="fas fa-users text-yellow-500 text-3xl"></i>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold">0</div>
              <p class="text-gray-400">Friends Referred</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Referral Code with Enhanced Sharing Options -->
      <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl p-6 mb-8 border border-gray-700 shadow-lg">
        <div class="mb-4">
          <h2 class="text-xl font-bold mb-2">Your Referral Code</h2>
          <p class="text-gray-400">Share your unique code with friends and earn rewards when they join!</p>
        </div>

        <div class="mb-6">
          <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
              <input type="text" id="referral_code" value="<?php echo $referral_code ?: 'Generating...'; ?>"
                class="bg-gray-800 border border-gray-600 hover:border-yellow-500 focus:border-yellow-500 rounded-lg py-4 px-4 w-full text-xl font-bold text-center text-white focus:outline-none transition-all duration-300" readonly>
            </div>
            <div class="flex space-x-2">
              <!-- Mobile Responsive Copy Button -->
              <button onclick="copyReferralCode()"
                class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold 
         py-2 sm:py-3 px-3 sm:px-5 rounded-lg 
         transition-all duration-300 transform hover:scale-105 
         w-full sm:w-auto flex items-center justify-center 
         text-sm sm:text-base"
                id="copy-code-btn">
                <i class="fas fa-copy mr-1 sm:mr-2"></i>
                <span class="hidden xs:inline">Copy</span>
                <span class="inline xs:hidden">Copy Code</span>
              </button>



              <div class="relative group">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 flex items-center justify-center" id="share-options-btn">
                  <i class="fas fa-share-alt mr-2"></i> Share
                </button>

                <div class="hidden group-hover:block absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg z-10 py-2 border border-gray-700">
                  <a href="#" onclick="shareViaWhatsApp(); return false;" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 flex items-center">
                    <i class="fab fa-whatsapp text-green-500 w-5 mr-3"></i> WhatsApp
                  </a>
                  <a href="#" onclick="shareViaFacebook(); return false;" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 flex items-center">
                    <i class="fab fa-facebook text-blue-500 w-5 mr-3"></i> Facebook
                  </a>
                  <a href="#" onclick="shareViaTwitter(); return false;" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 flex items-center">
                    <i class="fab fa-twitter text-blue-400 w-5 mr-3"></i> Twitter
                  </a>
                  <a href="#" onclick="shareViaEmail(); return false;" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 flex items-center">
                    <i class="fas fa-envelope text-red-400 w-5 mr-3"></i> Email
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Referral Message Template -->
        <div class="bg-gray-800 bg-opacity-50 rounded-lg p-4 border border-dashed border-gray-600 mb-4">
          <p class="text-gray-300 text-sm italic">
            "Hey! I'm earning rewards with this amazing platform. Use my referral code <span class="text-yellow-500 font-bold"><?php echo $referral_code ?: 'CODE'; ?></span> when you sign up and start earning too! Sign up here: <span class="text-blue-400 underline">https://example.com/register</span>"
          </p>
          <button onclick="copyReferralMessage()" class="mt-2 text-yellow-500 hover:text-yellow-400 text-sm flex items-center">
            <i class="fas fa-copy mr-1"></i> Copy this message
          </button>
        </div>
      </div>

      <!-- How It Works with Visual Enhancements -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-bold">How It Works</h2>
          <div class="hidden md:block text-yellow-500 text-sm font-medium">
            Simple 3-step process
          </div>
        </div>

        <div class="relative">
          <!-- Connecting Line (Desktop only) - Extended for 4 steps -->
          <div class="hidden md:block absolute top-24 left-0 w-full h-1 bg-gray-700 z-0"></div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 relative z-10 transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl">
              <div class="h-14 w-14 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-600 text-black flex items-center justify-center text-xl font-bold mb-4">1</div>
              <h3 class="text-lg font-semibold mb-2">Share Your Code</h3>
              <p class="text-gray-400">Share your unique referral code with friends via social media, messaging apps, or email.</p>
              <div class="mt-4 text-yellow-500">
                <i class="fas fa-share-alt text-2xl"></i>
              </div>
            </div>

            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 relative z-10 transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl">
              <div class="h-14 w-14 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-600 text-black flex items-center justify-center text-xl font-bold mb-4">2</div>
              <h3 class="text-lg font-semibold mb-2">Friend Registers</h3>
              <p class="text-gray-400">Your friend creates an account using your referral code during their registration process.</p>
              <div class="mt-4 text-yellow-500">
                <i class="fas fa-user-plus text-2xl"></i>
              </div>
            </div>

            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 relative z-10 transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl">
              <div class="h-14 w-14 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-600 text-black flex items-center justify-center text-xl font-bold mb-4">3</div>
              <h3 class="text-lg font-semibold mb-2">Get Sign-up Bonus</h3>
              <p class="text-gray-400">You receive $5 bonus added directly to your wallet for each successful referral!</p>              <div class="mt-4 text-yellow-500">
                <i class="fas fa-coins text-2xl"></i>
              </div>
            </div>

            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 relative z-10 transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl">
              <div class="h-14 w-14 rounded-full bg-gradient-to-r from-purple-500 to-purple-600 text-white flex items-center justify-center text-xl font-bold mb-4">4</div>
              <h3 class="text-lg font-semibold mb-2">Earn Commissions</h3>
              <p class="text-gray-400">Get a 5% commission on every plan your referred friends purchase - unlimited earnings!</p>
              <div class="mt-4 text-purple-500">
                <i class="fas fa-percentage text-2xl"></i>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- FAQ Section -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-8">
        <h2 class="text-xl font-bold mb-6">Frequently Asked Questions</h2>

        <div class="space-y-4">
          <div class="border-b border-gray-700 pb-4">
            <button class="faq-toggle flex justify-between items-center w-full text-left focus:outline-none">
              <span class="font-medium">How much can I earn with referrals?</span>
              <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
            </button>
            <div class="faq-content hidden mt-2">
            <p class="text-gray-400 text-sm">There's no limit to how much you can earn! You get $5 for each successful referral, and there's no maximum number of friends you can refer.</p>            </div>
          </div>

          <div class="border-b border-gray-700 pb-4">
            <button class="faq-toggle flex justify-between items-center w-full text-left focus:outline-none">
              <span class="font-medium">When do I receive my referral bonus?</span>
              <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
            </button>
            <div class="faq-content hidden mt-2">
            <p class="text-gray-400 text-sm">You receive your $5 bonus immediately after your referred friend successfully completes registration with your code.</p>            </div>
          </div>

          <div class="border-b border-gray-700 pb-4">
            <button class="faq-toggle flex justify-between items-center w-full text-left focus:outline-none">
              <span class="font-medium">Can I refer family members?</span>
              <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
            </button>
            <div class="faq-content hidden mt-2">
              <p class="text-gray-400 text-sm">Yes! You can refer anyone who doesn't already have an account - including family members, friends, or colleagues.</p>
            </div>
          </div>

          <div class="pb-2">
            <button class="faq-toggle flex justify-between items-center w-full text-left focus:outline-none">
              <span class="font-medium">How can I track my referrals?</span>
              <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
            </button>
            <div class="faq-content hidden mt-2">
              <p class="text-gray-400 text-sm">You can see all your successful referrals and earnings directly in this dashboard. The information is updated in real-time.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Success notification toast (hidden by default) -->
  <div id="success-toast" class="fixed bottom-4 right-4 bg-green-800 text-white px-4 py-3 rounded-lg shadow-lg flex items-center hidden transform transition-all duration-300 translate-y-10 opacity-0">
    <i class="fas fa-check-circle mr-2"></i>
    <span id="toast-message">Copied successfully!</span>
    <button onclick="hideToast()" class="ml-4 text-white">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <script>
    // Display the main content (remove the initial style="display: none;")
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('main-content').style.display = 'block';

      // Animate earnings counter
      const counter = document.getElementById('earnings-counter');
      let count = 0;
      const target = 0; // Set this to the actual earnings
      const duration = 1500;
      const interval = 30;
      const increment = target / (duration / interval);

      const timer = setInterval(() => {
        count += increment;
        if (count >= target) {
          clearInterval(timer);
          count = target;
        }
        counter.textContent = Math.floor(count);
      }, interval);

      // Setup FAQ toggles
      const faqToggles = document.querySelectorAll('.faq-toggle');
      faqToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
          const content = this.nextElementSibling;
          const icon = this.querySelector('i');

          if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('transform', 'rotate-180');
          } else {
            content.classList.add('hidden');
            icon.classList.remove('transform', 'rotate-180');
          }
        });
      });
    });

    // Copy referral code function
    function copyReferralCode() {
      const codeElement = document.getElementById('referral_code');
      navigator.clipboard.writeText(codeElement.value).then(() => {
        showToast('Referral code copied!');

        // Animate the button
        const button = document.getElementById('copy-code-btn');
        button.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
        button.classList.add('bg-green-500');

        setTimeout(() => {
          button.innerHTML = '<i class="fas fa-copy mr-2"></i> Copy Code';
          button.classList.remove('bg-green-500');
        }, 2000);
      });
    }

    // Copy referral message
    function copyReferralMessage() {
      const code = document.getElementById('referral_code').value;
      const message = `Hey! I'm earning rewards with this amazing platform. Use my referral code ${code} when you sign up and start earning too! Sign up here: https://example.com/register`;

      navigator.clipboard.writeText(message).then(() => {
        showToast('Referral message copied!');
      });
    }

    // Share functions
    function shareViaWhatsApp() {
      const code = document.getElementById('referral_code').value;
      const message = `Hey! I'm earning rewards with this amazing platform. Use my referral code ${code} when you sign up and start earning too! Sign up here: https://example.com/register`;
      const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
      window.open(url, '_blank');
    }

    function shareViaFacebook() {
      const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent('https://example.com/register')}`;
      window.open(url, '_blank');
    }

    function shareViaTwitter() {
      const code = document.getElementById('referral_code').value;
      const message = `Join me on this amazing platform and start earning rewards! Use my referral code ${code} when you sign up. 🚀`;
      const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(message)}&url=${encodeURIComponent('https://example.com/register')}`;
      window.open(url, '_blank');
    }

    function shareViaEmail() {
      const code = document.getElementById('referral_code').value;
      const subject = 'Join me and earn rewards!';
      const body = `Hey!\n\nI'm earning rewards with this amazing platform. Use my referral code ${code} when you sign up and start earning too!\n\nSign up here: https://example.com/register`;
      const url = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      window.location.href = url;
    }

    // Toast notification functions
    function showToast(message) {
      const toast = document.getElementById('success-toast');
      document.getElementById('toast-message').textContent = message;

      toast.classList.remove('hidden', 'translate-y-10', 'opacity-0');
      toast.classList.add('opacity-100');

      setTimeout(hideToast, 3000);
    }

    function hideToast() {
      const toast = document.getElementById('success-toast');
      toast.classList.add('translate-y-10', 'opacity-0');
      setTimeout(() => {
        toast.classList.add('hidden');
      }, 300);
    }
  </script>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    // Show loading animation when page loads
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1.5 seconds
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1500);
    });

    function copySimpleCode() {
      const code = document.getElementById('referral_code');
      const btn = document.getElementById('simple-copy-btn');

      // Create a temporary input to copy from
      const tempInput = document.createElement('input');
      tempInput.value = '<?php echo $referral_code; ?>';
      document.body.appendChild(tempInput);
      tempInput.select();
      document.execCommand('copy');
      document.body.removeChild(tempInput);

      // Change button text temporarily
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
      btn.classList.add('copy-animation');

      setTimeout(function() {
        btn.innerHTML = originalText;
        btn.classList.remove('copy-animation');
      }, 2000);
    }

    function copyReferralCode() {
      const code = document.getElementById('referral_code');
      const btn = document.getElementById('copy-code-btn');

      code.select();
      document.execCommand('copy');

      // Change button text temporarily
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
      btn.classList.add('copy-animation');

      setTimeout(function() {
        btn.innerHTML = originalText;
        btn.classList.remove('copy-animation');
      }, 2000);
    }
  </script>
</body>

</html>