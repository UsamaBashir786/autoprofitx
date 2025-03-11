<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
  $subject = trim($_POST['subject']);
  $category = trim($_POST['category']);
  $message = trim($_POST['message']);
  $priority = trim($_POST['priority']);

  // Validate inputs
  if (empty($subject) || empty($message) || empty($category)) {
    $error_message = "Please fill all required fields";
  } else {
    // Check if support_tickets table exists
    $check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_tickets'";
    $check_result = $conn->query($check_table_sql);
    $table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

    if (!$table_exists) {
      // Create support_tickets table
      $create_table_sql = "CREATE TABLE support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ticket_id VARCHAR(20) NOT NULL UNIQUE,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      )";

      $conn->query($create_table_sql);

      // Create support_responses table
      $create_responses_table_sql = "CREATE TABLE support_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NULL,
        admin_id INT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
      )";

      $conn->query($create_responses_table_sql);
    }

    // Generate ticket ID
    $ticket_id = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));

    // Insert ticket
    $insert_sql = "INSERT INTO support_tickets (user_id, ticket_id, subject, category, message, priority) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("isssss", $user_id, $ticket_id, $subject, $category, $message, $priority);

    if ($stmt->execute()) {
      $success_message = "Your support ticket has been submitted successfully. Ticket ID: " . $ticket_id;
    } else {
      $error_message = "Error submitting your ticket. Please try again.";
    }

    $stmt->close();
  }
}

// Get user's tickets
$tickets = [];
$check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_tickets'";
$check_result = $conn->query($check_table_sql);
$table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

if ($table_exists) {
  $tickets_sql = "SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC";
  $stmt = $conn->prepare($tickets_sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
  }

  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>AutoProftX - Support</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>

  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold">Support Center</h1>
        <p class="text-gray-400">Get help, submit tickets, and find answers to your questions</p>
      </div>

      <?php if (!empty($success_message)): ?>
        <div class="bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md mb-6 flex items-start">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md mb-6 flex items-start">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar / Navigation -->
        <div class="lg:col-span-1">
          <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden sticky top-24">
            <div class="p-4 border-b border-gray-700">
              <h3 class="font-bold text-lg">Support Options</h3>
            </div>
            <nav class="p-2">
              <a href="#new-ticket" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 support-nav-item active">
                <i class="fas fa-ticket-alt w-6 text-yellow-500"></i>
                <span class="ml-2">New Support Ticket</span>
              </a>
              <a href="#my-tickets" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 support-nav-item">
                <i class="fas fa-clipboard-list w-6 text-yellow-500"></i>
                <span class="ml-2">My Tickets</span>
              </a>
              <a href="#faq" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 support-nav-item">
                <i class="fas fa-question-circle w-6 text-yellow-500"></i>
                <span class="ml-2">FAQ</span>
              </a>
              <a href="#contact" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 support-nav-item">
                <i class="fas fa-phone-alt w-6 text-yellow-500"></i>
                <span class="ml-2">Contact Information</span>
              </a>
            </nav>
          </div>
        </div>

        <!-- Main Support Content -->
        <div class="lg:col-span-2">
          <!-- New Ticket Section -->
          <div id="new-ticket" class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8 support-section active">
            <h2 class="text-xl font-bold mb-6">Submit a New Support Ticket</h2>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
              <div class="space-y-4">
                <div>
                  <label for="subject" class="block text-sm font-medium text-gray-300 mb-1">Subject*</label>
                  <input type="text" id="subject" name="subject" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
                </div>

                <div>
                  <label for="category" class="block text-sm font-medium text-gray-300 mb-1">Category*</label>
                  <select id="category" name="category" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
                    <option value="">Select a category</option>
                    <option value="account">Account Issues</option>
                    <option value="deposit">Deposits</option>
                    <option value="withdrawal">Withdrawals</option>
                    <option value="investment">Investments</option>
                    <option value="technical">Technical Issues</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div>
                  <label for="priority" class="block text-sm font-medium text-gray-300 mb-1">Priority</label>
                  <select id="priority" name="priority" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>

                <div>
                  <label for="message" class="block text-sm font-medium text-gray-300 mb-1">Message*</label>
                  <textarea id="message" name="message" rows="6" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required></textarea>
                  <p class="text-xs text-gray-400 mt-1">Please provide as much detail as possible so we can better assist you.</p>
                </div>

                <div class="mt-6">
                  <button type="submit" name="submit_ticket" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-md transition duration-300">
                    Submit Ticket
                  </button>
                </div>
              </div>
            </form>
          </div>

          <!-- My Tickets Section -->
          <div id="my-tickets" class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8 support-section hidden">
            <h2 class="text-xl font-bold mb-6">My Support Tickets</h2>

            <?php if (empty($tickets)): ?>
              <div class="bg-gray-700 rounded-lg p-8 text-center">
                <i class="fas fa-ticket-alt text-yellow-500 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium mb-2">No Tickets Found</h3>
                <p class="text-gray-400 mb-4">You haven't submitted any support tickets yet.</p>
                <button class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-md transition duration-300 goto-new-ticket">
                  Create Your First Ticket
                </button>
              </div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                  <thead>
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Ticket ID</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Subject</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-700">
                    <?php foreach ($tickets as $ticket): ?>
                      <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo htmlspecialchars($ticket['ticket_id']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <?php
                          $status_classes = [
                            'open' => 'bg-blue-900 text-blue-200',
                            'in_progress' => 'bg-yellow-900 text-yellow-200',
                            'resolved' => 'bg-green-900 text-green-200',
                            'closed' => 'bg-gray-700 text-gray-300'
                          ];
                          $status_text = ucfirst(str_replace('_', ' ', $ticket['status']));
                          $status_class = $status_classes[$ticket['status']] ?? 'bg-gray-700 text-gray-300';
                          ?>
                          <span class="px-2 py-1 text-xs rounded-full <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                          <a href="ticket_details.php?id=<?php echo $ticket['id']; ?>" class="text-yellow-500 hover:text-yellow-400">
                            View Details
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- FAQ Section -->
          <div id="faq" class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8 support-section hidden">
            <h2 class="text-xl font-bold mb-6">Frequently Asked Questions</h2>

            <div class="space-y-4">
              <div class="border border-gray-700 rounded-lg overflow-hidden">
                <button class="w-full text-left px-4 py-3 bg-gray-700 flex justify-between items-center faq-toggle">
                  <span class="font-medium">How do I make a deposit?</span>
                  <i class="fas fa-chevron-down text-yellow-500 transition-transform duration-200"></i>
                </button>
                <div class="px-4 py-3 bg-gray-800 faq-content hidden">
                  <p class="text-gray-300">
                    To make a deposit, navigate to the "Deposit" section in your dashboard. Select your preferred payment method, enter the amount you wish to deposit, and follow the on-screen instructions. Our platform supports multiple payment methods including bank transfers, credit/debit cards, and cryptocurrencies.
                  </p>
                </div>
              </div>

              <div class="border border-gray-700 rounded-lg overflow-hidden">
                <button class="w-full text-left px-4 py-3 bg-gray-700 flex justify-between items-center faq-toggle">
                  <span class="font-medium">How long do withdrawals take to process?</span>
                  <i class="fas fa-chevron-down text-yellow-500 transition-transform duration-200"></i>
                </button>
                <div class="px-4 py-3 bg-gray-800 faq-content hidden">
                  <p class="text-gray-300">
                    Withdrawal processing times vary depending on the payment method:
                  </p>
                  <ul class="list-disc list-inside mt-2 space-y-1 text-gray-300">
                    <li>Cryptocurrency withdrawals: 1-24 hours</li>
                    <li>Bank transfers: 2-5 business days</li>
                    <li>Credit/debit cards: 3-7 business days</li>
                  </ul>
                  <p class="mt-2 text-gray-300">
                    Please note that verification may be required for larger withdrawal amounts for security purposes.
                  </p>
                </div>
              </div>

              <div class="border border-gray-700 rounded-lg overflow-hidden">
                <button class="w-full text-left px-4 py-3 bg-gray-700 flex justify-between items-center faq-toggle">
                  <span class="font-medium">How does the investment system work?</span>
                  <i class="fas fa-chevron-down text-yellow-500 transition-transform duration-200"></i>
                </button>
                <div class="px-4 py-3 bg-gray-800 faq-content hidden">
                  <p class="text-gray-300">
                    Our platform uses advanced algorithmic trading strategies to generate returns on your investments. You can choose from different investment plans based on your risk tolerance and investment goals. Each plan has different minimum investment requirements, expected returns, and investment periods.
                  </p>
                  <p class="mt-2 text-gray-300">
                    Profits are automatically calculated and added to your account balance according to the terms of your selected plan.
                  </p>
                </div>
              </div>

              <div class="border border-gray-700 rounded-lg overflow-hidden">
                <button class="w-full text-left px-4 py-3 bg-gray-700 flex justify-between items-center faq-toggle">
                  <span class="font-medium">Is my personal information secure?</span>
                  <i class="fas fa-chevron-down text-yellow-500 transition-transform duration-200"></i>
                </button>
                <div class="px-4 py-3 bg-gray-800 faq-content hidden">
                  <p class="text-gray-300">
                    Yes, we take data security very seriously. All personal information is encrypted and stored securely according to industry standards. We use advanced SSL encryption for all transactions and communications.
                  </p>
                  <p class="mt-2 text-gray-300">
                    We never share your personal information with third parties without your consent, except when required by law.
                  </p>
                </div>
              </div>

              <div class="border border-gray-700 rounded-lg overflow-hidden">
                <button class="w-full text-left px-4 py-3 bg-gray-700 flex justify-between items-center faq-toggle">
                  <span class="font-medium">How can I change my account settings?</span>
                  <i class="fas fa-chevron-down text-yellow-500 transition-transform duration-200"></i>
                </button>
                <div class="px-4 py-3 bg-gray-800 faq-content hidden">
                  <p class="text-gray-300">
                    To change your account settings:
                  </p>
                  <ol class="list-decimal list-inside mt-2 space-y-1 text-gray-300">
                    <li>Click on your profile icon in the top right corner</li>
                    <li>Select "Settings" from the dropdown menu</li>
                    <li>Navigate to the appropriate tab (Security, Notifications, Appearance, etc.)</li>
                    <li>Make your desired changes and save</li>
                  </ol>
                  <p class="mt-2 text-gray-300">
                    For security-related changes like password updates, you may be required to verify your identity.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Contact Section -->
          <div id="contact" class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8 support-section hidden">
            <h2 class="text-xl font-bold mb-6">Contact Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="bg-gray-700 p-5 rounded-lg">
                <div class="flex items-start">
                  <div class="bg-yellow-500 rounded-full p-3 text-black mr-4">
                    <i class="fas fa-envelope"></i>
                  </div>
                  <div>
                    <h3 class="font-semibold mb-2">Email Support</h3>
                    <p class="text-gray-300 mb-2">Send us an email and we'll respond within 24 hours.</p>
                    <a href="mailto:support@autoproftx.com" class="text-yellow-500 hover:underline">support@autoproftx.com</a>
                  </div>
                </div>
              </div>

              <div class="bg-gray-700 p-5 rounded-lg">
                <div class="flex items-start">
                  <div class="bg-yellow-500 rounded-full p-3 text-black mr-4">
                    <i class="fas fa-phone-alt"></i>
                  </div>
                  <div>
                    <h3 class="font-semibold mb-2">Phone Support</h3>
                    <p class="text-gray-300 mb-2">Call us during business hours (9AM-5PM GMT).</p>
                    <a href="tel:+1234567890" class="text-yellow-500 hover:underline">+1 (234) 567-890</a>
                  </div>
                </div>
              </div>

              <div class="bg-gray-700 p-5 rounded-lg">
                <div class="flex items-start">
                  <div class="bg-yellow-500 rounded-full p-3 text-black mr-4">
                    <i class="fas fa-comments"></i>
                  </div>
                  <div>
                    <h3 class="font-semibold mb-2">Live Chat</h3>
                    <p class="text-gray-300 mb-2">Chat with our support team in real-time.</p>
                    <button class="text-yellow-500 hover:underline">Start Live Chat</button>
                  </div>
                </div>
              </div>

              <div class="bg-gray-700 p-5 rounded-lg">
                <div class="flex items-start">
                  <div class="bg-yellow-500 rounded-full p-3 text-black mr-4">
                    <i class="fab fa-telegram"></i>
                  </div>
                  <div>
                    <h3 class="font-semibold mb-2">Telegram Community</h3>
                    <p class="text-gray-300 mb-2">Join our Telegram group for updates and community support.</p>
                    <a href="#" class="text-yellow-500 hover:underline">Join Telegram Group</a>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-8 bg-gray-700 p-6 rounded-lg">
              <h3 class="font-semibold mb-4">Business Hours</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <h4 class="text-sm font-medium text-gray-400 mb-2">Customer Support</h4>
                  <ul class="space-y-1 text-gray-300">
                    <li class="flex justify-between">
                      <span>Monday - Friday:</span>
                      <span>9:00 AM - 5:00 PM GMT</span>
                    </li>
                    <li class="flex justify-between">
                      <span>Saturday:</span>
                      <span>10:00 AM - 2:00 PM GMT</span>
                    </li>
                    <li class="flex justify-between">
                      <span>Sunday:</span>
                      <span>Closed</span>
                    </li>
                  </ul>
                </div>

                <div>
                  <h4 class="text-sm font-medium text-gray-400 mb-2">Technical Support</h4>
                  <ul class="space-y-1 text-gray-300">
                    <li class="flex justify-between">
                      <span>Monday - Friday:</span>
                      <span>24/7</span>
                    </li>
                    <li class="flex justify-between">
                      <span>Saturday - Sunday:</span>
                      <span>10:00 AM - 6:00 PM GMT</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Show content once page is loaded
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1000);

      // Handle support navigation
      const navItems = document.querySelectorAll('.support-nav-item');
      const sections = document.querySelectorAll('.support-section');

      navItems.forEach(item => {
        item.addEventListener('click', function(e) {
          e.preventDefault();

          // Get the target section id from href
          const targetId = this.getAttribute('href').substring(1);

          // Remove active class from all nav items and sections
          navItems.forEach(nav => nav.classList.remove('active', 'bg-gray-700'));
          sections.forEach(section => section.classList.add('hidden'));

          // Add active class to clicked nav item and show target section
          this.classList.add('active', 'bg-gray-700');
          document.getElementById(targetId).classList.remove('hidden');
        });
      });

      // Handle "Create Your First Ticket" button
      const createTicketBtn = document.querySelector('.goto-new-ticket');
      if (createTicketBtn) {
        createTicketBtn.addEventListener('click', function() {
          // Trigger click on the new ticket nav item
          document.querySelector('a[href="#new-ticket"]').click();
        });
      }

      // Handle FAQ toggles
      const faqToggles = document.querySelectorAll('.faq-toggle');

      faqToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
          const content = this.nextElementSibling;
          const icon = this.querySelector('i');

          // Toggle content visibility
          content.classList.toggle('hidden');

          // Rotate icon
          if (content.classList.contains('hidden')) {
            icon.style.transform = 'rotate(0deg)';
          } else {
            icon.style.transform = 'rotate(180deg)';
          }

          // Close other FAQs
          faqToggles.forEach(otherToggle => {
            if (otherToggle !== this) {
              const otherContent = otherToggle.nextElementSibling;
              const otherIcon = otherToggle.querySelector('i');

              otherContent.classList.add('hidden');
              otherIcon.style.transform = 'rotate(0deg)';
            }
          });
        });
      });

      // Show section from URL hash if present
      if (window.location.hash) {
        const targetId = window.location.hash.substring(1);
        const targetNav = document.querySelector(`.support-nav-item[href="#${targetId}"]`);
        const targetSection = document.getElementById(targetId);

        if (targetNav && targetSection) {
          navItems.forEach(nav => nav.classList.remove('active', 'bg-gray-700'));
          sections.forEach(section => section.classList.add('hidden'));

          targetNav.classList.add('active', 'bg-gray-700');
          targetSection.classList.remove('hidden');
        }
      }
    });
  </script>
</body>

</html>