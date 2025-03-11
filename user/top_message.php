<?php
// Include database connection
include '../config/db.php';
// Make sure $conn is available from the included file

// Check user authentication
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

// Initialize variables
$message = '';
$error = '';
$userId = $_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'User';
$chatHistory = [];

// Check for message status in session (from redirect)
if (isset($_SESSION['message'])) {
  $message = $_SESSION['message'];
  unset($_SESSION['message']); // Clear the message after displaying
}
if (isset($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']); // Clear the error after displaying
}

// Process message sending
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
  $messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

  if (!empty($messageText)) {
    // Use the global $conn connection from the included db.php file
    global $conn;

    try {
      // Get assigned admin for this user (or find the first admin if none assigned)
      $adminId = null;
      $stmt = $conn->prepare("
        SELECT admin_id FROM admin_messages 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
      ");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $adminData = $result->fetch_assoc();
        $adminId = $adminData['admin_id'];
      } else {
        // If no previous messages, find an admin
        $result = $conn->query("SELECT id FROM admins LIMIT 1");
        if ($result->num_rows > 0) {
          $adminData = $result->fetch_assoc();
          $adminId = $adminData['id'];
        }
      }

      if ($adminId) {
        // Insert the message
        $stmt = $conn->prepare("
          INSERT INTO admin_messages (admin_id, user_id, message, sent_by) 
          VALUES (?, ?, ?, 'user')
        ");
        $stmt->bind_param("iis", $adminId, $userId, $messageText);
        $stmt->execute();

        $_SESSION['message'] = "Message sent successfully.";
      } else {
        $_SESSION['error'] = "No admin available to receive your message.";
      }
    } catch (Exception $e) {
      $_SESSION['error'] = "Error sending message: " . $e->getMessage();
    }
  } else {
    $_SESSION['error'] = "Please enter a message.";
  }

  // Redirect to prevent form resubmission on refresh
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Get chat history
function getChatHistory($userId)
{
  // Use the global $conn connection from the included db.php file
  global $conn;
  $history = [];

  try {
    // Get assigned admin for this user
    $adminId = null;
    $stmt = $conn->prepare("
      SELECT DISTINCT admin_id 
      FROM admin_messages 
      WHERE user_id = ? 
      ORDER BY created_at DESC 
      LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $adminData = $result->fetch_assoc();
      $adminId = $adminData['admin_id'];
    } else {
      return [];
    }

    // Get chat history with this admin
    $stmt = $conn->prepare("
      SELECT 
        id,
        admin_id,
        user_id,
        message,
        sent_by,
        created_at
      FROM 
        admin_messages
      WHERE 
        admin_id = ? AND user_id = ?
      ORDER BY 
        created_at ASC
    ");
    $stmt->bind_param("ii", $adminId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $history[] = $row;
    }

    return $history;
  } catch (Exception $e) {
    error_log("Error getting chat history: " . $e->getMessage());
    return [];
  }
}

// Get user information
function getUserInfo($userId)
{
  // Use the global $conn connection from the included db.php file
  global $conn;

  try {
    $stmt = $conn->prepare("
      SELECT 
        id, full_name, email, referral_code, status,
        (SELECT balance FROM wallets WHERE user_id = u.id) as balance
      FROM 
        users u
      WHERE 
        id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
      return $row;
    }

    return null;
  } catch (Exception $e) {
    error_log("Error getting user info: " . $e->getMessage());
    return null;
  }
}

// Get number of unread admin responses (for notification)
function getUnreadMessagesCount($userId)
{
  // Use the global $conn connection from the included db.php file
  global $conn;
  $count = 0;

  try {
    $stmt = $conn->prepare("
      SELECT COUNT(*) as count
      FROM admin_messages
      WHERE user_id = ? AND sent_by = 'admin' AND `read` = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $count = $row['count'];
    }

    // Mark messages as read
    $updateStmt = $conn->prepare("
      UPDATE admin_messages 
      SET `read` = 1 
      WHERE user_id = ? AND sent_by = 'admin' AND `read` = 0
    ");
    $updateStmt->bind_param("i", $userId);
    $updateStmt->execute();
  } catch (Exception $e) {
    error_log("Error getting unread messages: " . $e->getMessage());
  }

  return $count;
}

// Get data
$chatHistory = getChatHistory($userId);
$userInfo = getUserInfo($userId);
$unreadCount = getUnreadMessagesCount($userId);
?>

<!-- The rest of your HTML code remains the same -->
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Support Chat - User Dashboard</title>
  <style>
    .chat-bubble {
      position: relative;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      max-width: 80%;
      margin-bottom: 1rem;
    }

    .chat-bubble::after {
      content: '';
      position: absolute;
      width: 0;
      height: 0;
      bottom: -10px;
      border: 10px solid transparent;
    }

    .chat-bubble-user {
      background-color: #0ea5e9;
      color: white;
      margin-left: auto;
    }

    .chat-bubble-user::after {
      border-top-color: #0ea5e9;
      right: 15px;
    }

    .chat-bubble-admin {
      background-color: #374151;
      color: white;
      margin-right: auto;
    }

    .chat-bubble-admin::after {
      border-top-color: #374151;
      left: 15px;
    }

    .chat-container {
      height: calc(100vh - 350px);
      min-height: 400px;
      overflow-y: auto;
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main id="main-content" class="flex-grow py-4 sm:py-6">
    <div class="container mx-auto px-3 sm:px-4 lg:px-8 max-w-5xl">
      <!-- Page Header -->
      <div class="mb-4 sm:mb-8 flex items-center space-x-3">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 sm:w-8 sm:h-8 text-blue-500">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <h1 class="text-lg sm:text-xl md:text-2xl font-bold">Support Chat</h1>
      </div>

      <!-- Chat Interface -->
      <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <!-- Chat Header -->
        <div class="p-3 sm:p-4 border-b border-gray-700 bg-gray-900 sticky top-0 z-10">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-green-600 flex items-center justify-center">
                <i class="fas fa-headset text-white text-sm sm:text-base"></i>
              </div>
              <div class="ml-2 sm:ml-3">
                <h3 class="text-base sm:text-lg font-semibold">Support Team</h3>
                <p class="text-xs text-gray-400 hidden sm:block">Usually replies within 1 hour</p>
              </div>
            </div>
            <div class="flex items-center space-x-2 sm:space-x-3">
              <span id="online-status" class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-900 text-green-300">
                <span class="w-2 h-2 mr-1 bg-green-400 rounded-full"></span>
                Online
              </span>
              <?php if (!empty($chatHistory)): ?>
                <button onclick="fetchNewMessages()" class="p-2 text-gray-400 hover:text-white transition-colors rounded-full hover:bg-gray-700" aria-label="Refresh messages">
                  <i class="fas fa-sync-alt"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Chat Messages -->
        <div class="chat-container p-3 sm:p-4" id="chat-container">
          <?php if (empty($chatHistory)): ?>
            <div class="flex items-center justify-center h-full">
              <div class="text-center text-gray-500 py-8">
                <i class="fas fa-comments text-4xl sm:text-5xl mb-4"></i>
                <p class="text-lg sm:text-xl font-semibold mb-2">Welcome to Support</p>
                <p class="max-w-md mx-auto mb-4 px-4 text-sm sm:text-base">How can we help you today? Send us a message and our team will assist you as soon as possible.</p>
                <div class="flex flex-wrap justify-center gap-2 sm:gap-4 text-xs sm:text-sm px-2">
                  <button onclick="insertQuickQuestion('I need help with my account')" class="bg-gray-700 p-2 sm:p-3 rounded-lg hover:bg-gray-600 transition">
                    <i class="fas fa-question-circle text-blue-400 mr-2"></i> Account Issues
                  </button>
                  <button onclick="insertQuickQuestion('I have a payment question')" class="bg-gray-700 p-2 sm:p-3 rounded-lg hover:bg-gray-600 transition">
                    <i class="fas fa-dollar-sign text-green-400 mr-2"></i> Payment Help
                  </button>
                  <button onclick="insertQuickQuestion('How does the referral program work?')" class="bg-gray-700 p-2 sm:p-3 rounded-lg hover:bg-gray-600 transition">
                    <i class="fas fa-user-plus text-yellow-400 mr-2"></i> Referral Program
                  </button>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div id="messages-container">
              <?php foreach ($chatHistory as $chat): ?>
                <div class="flex flex-col <?php echo ($chat['sent_by'] == 'user') ? 'items-end' : 'items-start'; ?> message-item animate-fade-in">
                  <div class="chat-bubble <?php echo ($chat['sent_by'] == 'user') ? 'chat-bubble-user' : 'chat-bubble-admin'; ?> break-words">
                    <?php echo nl2br(htmlspecialchars($chat['message'])); ?>
                  </div>
                  <div class="text-xs text-gray-500 <?php echo ($chat['sent_by'] == 'user') ? 'text-right' : 'text-left'; ?> mb-4 mt-1 px-2">
                    <?php echo date('M d, Y H:i', strtotime($chat['created_at'])); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div id="new-message-indicator" class="hidden fixed bottom-32 right-4 md:right-8 bg-blue-600 text-white rounded-full p-2 shadow-lg cursor-pointer z-20">
              <i class="fas fa-arrow-down mr-1"></i> New messages
            </div>
          <?php endif; ?>
        </div>

        <!-- Message Input -->
        <div class="p-3 sm:p-4 border-t border-gray-700 bg-gray-900 sticky bottom-0 z-10">
          <?php if (!empty($message)): ?>
            <div class="mb-3 p-2 sm:p-3 bg-green-900 text-green-100 rounded-lg text-sm">
              <?php echo $message; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($error)): ?>
            <div class="mb-3 p-2 sm:p-3 bg-red-900 text-red-100 rounded-lg text-sm">
              <?php echo $error; ?>
            </div>
          <?php endif; ?>

          <form action="" method="POST" class="flex gap-2 sm:gap-3" id="message-form">
            <div class="relative flex-1">
              <textarea
                name="message_text"
                id="message-input"
                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2 sm:p-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                rows="2"
                placeholder="Type your message here..."
                required
                onkeydown="handleKeyDown(event)"></textarea>
              <div class="absolute bottom-2 right-2 flex space-x-1 text-gray-400">
                <button type="button" id="emoji-button" class="p-1 hover:text-white focus:outline-none" aria-label="Insert emoji">
                  <i class="far fa-smile"></i>
                </button>
                <button type="button" id="attachment-button" class="p-1 hover:text-white focus:outline-none" aria-label="Attach file">
                  <i class="fas fa-paperclip"></i>
                </button>
              </div>
            </div>
            <button
              type="submit"
              name="send_message"
              class="px-3 sm:px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg shadow-lg transition duration-300 flex items-center self-end">
              <i class="fas fa-paper-plane sm:mr-2"></i>
              <span class="hidden sm:inline">Send</span>
            </button>
          </form>

          <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span>Press Enter to send, Shift+Enter for new line</span>
            <span id="typing-indicator" class="hidden">Agent is typing...</span>
          </div>
        </div>
      </div>

      <!-- Help & FAQ Section -->
      <div class="mt-6 sm:mt-8">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-gray-300">Help Center</h2>
          <button id="toggle-help" class="text-blue-400 text-sm flex items-center">
            <span id="toggle-help-text">Hide</span>
            <i id="toggle-help-icon" class="fas fa-chevron-up ml-1"></i>
          </button>
        </div>

        <div id="help-content" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-6">
          <!-- Quick Links -->
          <div class="bg-gray-800 rounded-lg p-3 sm:p-4 shadow-lg border border-gray-700">
            <h3 class="text-base sm:text-lg font-semibold mb-2 sm:mb-3 text-yellow-500">Quick Links</h3>
            <ul class="space-y-1 sm:space-y-2 text-sm">
              <li><a href="faq.php" class="text-blue-400 hover:text-blue-300 flex items-center p-1 hover:bg-gray-700 rounded transition-colors"><i class="fas fa-question-circle mr-2"></i> Frequently Asked Questions</a></li>
              <li><a href="terms.php" class="text-blue-400 hover:text-blue-300 flex items-center p-1 hover:bg-gray-700 rounded transition-colors"><i class="fas fa-file-contract mr-2"></i> Terms & Conditions</a></li>
              <li><a href="privacy.php" class="text-blue-400 hover:text-blue-300 flex items-center p-1 hover:bg-gray-700 rounded transition-colors"><i class="fas fa-shield-alt mr-2"></i> Privacy Policy</a></li>
              <li><a href="tutorial.php" class="text-blue-400 hover:text-blue-300 flex items-center p-1 hover:bg-gray-700 rounded transition-colors"><i class="fas fa-play-circle mr-2"></i> How to Get Started</a></li>
            </ul>
          </div>

          <!-- Common Issues -->
          <div class="bg-gray-800 rounded-lg p-3 sm:p-4 shadow-lg border border-gray-700">
            <h3 class="text-base sm:text-lg font-semibold mb-2 sm:mb-3 text-yellow-500">Common Issues</h3>
            <div class="space-y-2 text-sm">
              <div class="bg-gray-700 p-2 sm:p-3 rounded hover:bg-gray-600 cursor-pointer transition-colors" onclick="insertQuickQuestion('I have an issue with withdrawals')">
                <h4 class="font-medium">Withdrawal Issues</h4>
                <p class="text-gray-400 text-xs sm:text-sm">Having trouble with withdrawals? Check your payment method details and try again.</p>
              </div>
              <div class="bg-gray-700 p-2 sm:p-3 rounded hover:bg-gray-600 cursor-pointer transition-colors" onclick="insertQuickQuestion('When will my investment mature?')">
                <h4 class="font-medium">Investment Status</h4>
                <p class="text-gray-400 text-xs sm:text-sm">Investments may take up to 24 hours to mature and process returns.</p>
              </div>
              <div class="bg-gray-700 p-2 sm:p-3 rounded hover:bg-gray-600 cursor-pointer transition-colors" onclick="insertQuickQuestion('How do I earn referral commissions?')">
                <h4 class="font-medium">Referral Program</h4>
                <p class="text-gray-400 text-xs sm:text-sm">Commissions are automatically credited when your referrals make investments.</p>
              </div>
            </div>
          </div>

          <!-- Contact Options -->
          <div class="bg-gray-800 rounded-lg p-3 sm:p-4 shadow-lg border border-gray-700 sm:col-span-2 lg:col-span-1">
            <h3 class="text-base sm:text-lg font-semibold mb-2 sm:mb-3 text-yellow-500">Other Ways to Contact Us</h3>
            <div class="space-y-3 sm:space-y-4 text-sm">
              <div class="flex items-start">
                <div class="flex-shrink-0 h-6 w-6 sm:h-8 sm:w-8 bg-blue-900 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                  <i class="fas fa-envelope text-blue-300 text-xs sm:text-sm"></i>
                </div>
                <div>
                  <h4 class="font-medium">Email Support</h4>
                  <p class="text-gray-400 text-xs sm:text-sm">support@example.com</p>
                  <p class="text-gray-500 text-xs">Response time: Within 24 hours</p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="flex-shrink-0 h-6 w-6 sm:h-8 sm:w-8 bg-green-900 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                  <i class="fab fa-whatsapp text-green-300 text-xs sm:text-sm"></i>
                </div>
                <div>
                  <h4 class="font-medium">WhatsApp Support</h4>
                  <p class="text-gray-400 text-xs sm:text-sm">+1 234 567 8900</p>
                  <p class="text-gray-500 text-xs">Available 9 AM - 6 PM EST</p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="flex-shrink-0 h-6 w-6 sm:h-8 sm:w-8 bg-purple-900 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                  <i class="fab fa-telegram text-purple-300 text-xs sm:text-sm"></i>
                </div>
                <div>
                  <h4 class="font-medium">Telegram Group</h4>
                  <p class="text-gray-400 text-xs sm:text-sm">@examplesupport</p>
                  <p class="text-gray-500 text-xs">Join our community for quick help</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <style>
    .chat-bubble {
      position: relative;
      padding: 0.75rem 1rem;
      border-radius: 0.75rem;
      max-width: 80%;
      margin-bottom: 0.25rem;
      word-break: break-word;
    }

    @media (max-width: 640px) {
      .chat-bubble {
        max-width: 90%;
        padding: 0.5rem 0.75rem;
      }
    }

    .chat-bubble::after {
      content: '';
      position: absolute;
      width: 0;
      height: 0;
      bottom: -8px;
      border: 8px solid transparent;
    }

    .chat-bubble-user {
      background-color: #0ea5e9;
      color: white;
      margin-left: auto;
      border-bottom-right-radius: 0;
    }

    .chat-bubble-user::after {
      border-top-color: #0ea5e9;
      right: 0;
      border-right: 0;
    }

    .chat-bubble-admin {
      background-color: #374151;
      color: white;
      margin-right: auto;
      border-bottom-left-radius: 0;
    }

    .chat-bubble-admin::after {
      border-top-color: #374151;
      left: 0;
      border-left: 0;
    }

    .chat-container {
      height: calc(100vh - 390px);
      min-height: 300px;
      max-height: 600px;
      overflow-y: auto;
      scroll-behavior: smooth;
    }

    @media (max-width: 640px) {
      .chat-container {
        height: calc(100vh - 320px);
        min-height: 250px;
      }
    }

    /* Smooth animation for new messages */
    .animate-fade-in {
      animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Custom scrollbar for chat container */
    .chat-container::-webkit-scrollbar {
      width: 6px;
    }

    .chat-container::-webkit-scrollbar-track {
      background: #1f2937;
      border-radius: 4px;
    }

    .chat-container::-webkit-scrollbar-thumb {
      background: #4b5563;
      border-radius: 4px;
    }

    .chat-container::-webkit-scrollbar-thumb:hover {
      background: #6b7280;
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1 second
      setTimeout(function() {
        const preloader = document.querySelector('.preloader');
        if (preloader) {
          preloader.style.display = 'none';
        }
        document.getElementById('main-content').style.display = 'block';
      }, 1000);

      // Auto-scroll to bottom of chat
      scrollToBottom();

      // Toggle Help Center section
      const toggleHelpBtn = document.getElementById('toggle-help');
      const helpContent = document.getElementById('help-content');
      const toggleHelpText = document.getElementById('toggle-help-text');
      const toggleHelpIcon = document.getElementById('toggle-help-icon');

      if (toggleHelpBtn && helpContent) {
        toggleHelpBtn.addEventListener('click', function() {
          if (helpContent.style.display === 'none') {
            helpContent.style.display = 'grid';
            toggleHelpText.textContent = 'Hide';
            toggleHelpIcon.classList.remove('fa-chevron-down');
            toggleHelpIcon.classList.add('fa-chevron-up');
          } else {
            helpContent.style.display = 'none';
            toggleHelpText.textContent = 'Show';
            toggleHelpIcon.classList.remove('fa-chevron-up');
            toggleHelpIcon.classList.add('fa-chevron-down');
          }
        });
      }

      // Setup new message indicator
      const chatContainer = document.getElementById('chat-container');
      const newMessageIndicator = document.getElementById('new-message-indicator');

      if (chatContainer && newMessageIndicator) {
        // Check if user has scrolled up and new messages arrived
        chatContainer.addEventListener('scroll', function() {
          const isScrolledToBottom = chatContainer.scrollHeight - chatContainer.clientHeight - chatContainer.scrollTop < 50;

          if (isScrolledToBottom && newMessageIndicator.classList.contains('block')) {
            newMessageIndicator.classList.remove('block');
            newMessageIndicator.classList.add('hidden');
          }
        });

        // Click on indicator to scroll to bottom
        newMessageIndicator.addEventListener('click', function() {
          scrollToBottom();
          newMessageIndicator.classList.remove('block');
          newMessageIndicator.classList.add('hidden');
        });
      }

      // Setup emoji button (placeholder - would need a proper emoji picker)
      const emojiButton = document.getElementById('emoji-button');
      if (emojiButton) {
        emojiButton.addEventListener('click', function() {
          alert('Emoji picker would appear here.');
        });
      }

      // Setup attachment button (placeholder)
      const attachmentButton = document.getElementById('attachment-button');
      if (attachmentButton) {
        attachmentButton.addEventListener('click', function() {
          alert('File upload dialog would appear here.');
        });
      }
    });

    // Function to scroll chat to bottom
    function scrollToBottom() {
      const chatContainer = document.getElementById('chat-container');
      if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
      }
    }

    // Handle Enter key for message submission
    function handleKeyDown(event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        document.getElementById('message-form').submit();
      }
    }

    // Insert quick question into the input field
    function insertQuickQuestion(question) {
      const messageInput = document.getElementById('message-input');
      if (messageInput) {
        messageInput.value = question;
        messageInput.focus();
        // Scroll input to the end
        messageInput.scrollTop = messageInput.scrollHeight;
      }
    }

    // Optional: AJAX message fetch for refresh button
    window.fetchNewMessages = function() {
      // Display a loading indicator
      const refreshButton = document.querySelector('.fa-sync-alt');
      refreshButton.classList.add('fa-spin');

      // Create an AJAX request to get new messages
      const xhr = new XMLHttpRequest();
      xhr.open('GET', 'get_messages.php', true);
      xhr.onload = function() {
        if (this.status === 200) {
          try {
            const response = JSON.parse(this.responseText);
            if (response.success) {
              // Store current scroll position and check if at bottom
              const chatContainer = document.getElementById('chat-container');
              const isScrolledToBottom = chatContainer.scrollHeight - chatContainer.clientHeight - chatContainer.scrollTop < 50;

              // Update chat container with new messages
              const messagesContainer = document.getElementById('messages-container');
              if (messagesContainer) {
                messagesContainer.innerHTML = response.html;
              } else {
                chatContainer.innerHTML = response.html;
              }

              // If was scrolled to bottom before update, scroll to bottom again
              // Otherwise show the new message indicator
              if (isScrolledToBottom) {
                scrollToBottom();
              } else {
                const newMessageIndicator = document.getElementById('new-message-indicator');
                if (newMessageIndicator) {
                  newMessageIndicator.classList.remove('hidden');
                  newMessageIndicator.classList.add('block');
                }
              }

              // Show agent typing indicator for a moment (simulating activity)
              simulateAgentTyping();
            }
          } catch (e) {
            console.error('Error parsing response:', e);
          }
          // Remove the spinning icon
          refreshButton.classList.remove('fa-spin');
        }
      };
      xhr.onerror = function() {
        // Remove the spinning icon on error
        refreshButton.classList.remove('fa-spin');
      };
      xhr.send();
    };

    // Simulate agent typing indicator
    function simulateAgentTyping() {
      const typingIndicator = document.getElementById('typing-indicator');
      if (typingIndicator) {
        typingIndicator.classList.remove('hidden');
        setTimeout(function() {
          typingIndicator.classList.add('hidden');
        }, 3000);
      }
    }
  </script>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1 second
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1000);

      // Auto-scroll to bottom of chat
      const chatContainer = document.getElementById('chat-container');
      if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
      }

      // Optional: AJAX message fetch for refresh button
      window.fetchNewMessages = function() {
        // Display a loading indicator
        const refreshButton = document.querySelector('.fa-sync-alt');
        refreshButton.classList.add('fa-spin');

        // Create an AJAX request to get new messages
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_messages.php', true);
        xhr.onload = function() {
          if (this.status === 200) {
            try {
              const response = JSON.parse(this.responseText);
              if (response.success) {
                // Update chat container with new messages
                const chatContainer = document.getElementById('chat-container');
                chatContainer.innerHTML = response.html;
                chatContainer.scrollTop = chatContainer.scrollHeight;
              }
            } catch (e) {
              console.error('Error parsing response:', e);
            }
            // Remove the spinning icon
            refreshButton.classList.remove('fa-spin');
          }
        };
        xhr.onerror = function() {
          // Remove the spinning icon on error
          refreshButton.classList.remove('fa-spin');
        };
        xhr.send();
      };

      // Optional: Submit form via AJAX to prevent page reload
      const messageForm = document.getElementById('message-form');
      if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
          // Only if you want to implement AJAX submission
          // Otherwise, the POST-redirect-GET pattern will handle it
        });
      }
    });
  </script>
</body>

</html>