<?php
// Include database connection and tree commission system
include '../config/db.php';
require_once('../tree_commission.php');

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php");
  exit();
}

// Initialize variables
$message = '';
$error = '';
$selectedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$userInfo = null;
$chatHistory = [];

// Process message sending
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
  $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
  $messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

  if ($userId && !empty($messageText)) {
    $conn = getConnection();

    try {
      $adminId = $_SESSION['admin_id'];

      // Insert the message
      $stmt = $conn->prepare("
        INSERT INTO admin_messages (admin_id, user_id, message, sent_by) 
        VALUES (?, ?, ?, 'admin')
      ");
      $stmt->bind_param("iis", $adminId, $userId, $messageText);
      $stmt->execute();

      $message = "Message sent successfully.";
    } catch (Exception $e) {
      $error = "Error sending message: " . $e->getMessage();
    } finally {
      $conn->close();
    }
  } else {
    $error = "Please select a user and enter a message.";
  }
}

// Get top earners for messaging
function getTopEarners($limit = 20)
{
  $conn = getConnection();
  $users = [];

  try {
    $result = $conn->query("
      SELECT 
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.referral_code,
        COUNT(DISTINCT rt.user_id) as referral_count,
        COALESCE(SUM(tc.commission_amount), 0) as total_earnings,
        (SELECT COUNT(*) FROM admin_messages WHERE user_id = u.id AND sent_by = 'user' AND `read` = 0) as unread_count
      FROM 
        users u
        LEFT JOIN referral_tree rt ON u.id = rt.parent_id
        LEFT JOIN tree_commissions tc ON u.id = tc.user_id
      GROUP BY 
        u.id, u.full_name, u.email, u.phone, u.referral_code
      ORDER BY 
        total_earnings DESC, referral_count DESC
      LIMIT $limit
    ");

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $users[] = $row;
      }
    }

    return $users;
  } catch (Exception $e) {
    error_log("Error getting top earners: " . $e->getMessage());
    return [];
  } finally {
    $conn->close();
  }
}

// Get user information
function getUserInfo($userId)
{
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
      SELECT 
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.referral_code,
        (SELECT balance FROM wallets WHERE user_id = u.id) as balance,
        (SELECT COUNT(*) FROM referral_tree WHERE parent_id = u.id) as referral_count,
        (SELECT COALESCE(SUM(commission_amount), 0) FROM tree_commissions WHERE user_id = u.id) as total_earnings
      FROM 
        users u
      WHERE 
        u.id = ?
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
  } finally {
    $conn->close();
  }
}

// Get chat history
function getChatHistory($userId)
{
  $conn = getConnection();
  $history = [];

  try {
    $adminId = $_SESSION['admin_id'];

    // Mark user messages as read
    $stmt = $conn->prepare("
      UPDATE admin_messages 
      SET `read` = 1 
      WHERE admin_id = ? AND user_id = ? AND sent_by = 'user' AND `read` = 0
    ");
    $stmt->bind_param("ii", $adminId, $userId);
    $stmt->execute();

    // Get chat history
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
  } finally {
    $conn->close();
  }
}

// Get unread messages count for notification
function getUnreadMessagesCount()
{
  $conn = getConnection();
  $count = 0;

  try {
    $adminId = $_SESSION['admin_id'];
    $stmt = $conn->prepare("
      SELECT COUNT(*) as count
      FROM admin_messages
      WHERE admin_id = ? AND sent_by = 'user' AND `read` = 0
    ");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $count = $row['count'];
    }
  } catch (Exception $e) {
    error_log("Error getting unread messages: " . $e->getMessage());
  } finally {
    $conn->close();
  }

  return $count;
}

// Get data
$topEarners = getTopEarners();
$unreadCount = getUnreadMessagesCount();

if ($selectedUserId) {
  $userInfo = getUserInfo($selectedUserId);
  if ($userInfo) {
    $chatHistory = getChatHistory($selectedUserId);
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Messages - Tree Commission System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#f0f9ff',
              100: '#e0f2fe',
              500: '#0ea5e9',
              600: '#0284c7',
              700: '#0369a1',
            },
          }
        }
      }
    }
  </script>
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

    .chat-bubble-admin {
      background-color: #e0f2fe;
      color: #0369a1;
      margin-left: auto;
    }

    .chat-bubble-admin::after {
      border-top-color: #e0f2fe;
      right: 15px;
    }

    .chat-bubble-user {
      background-color: #f3f4f6;
      color: #4b5563;
      margin-right: auto;
    }

    .chat-bubble-user::after {
      border-top-color: #f3f4f6;
      left: 15px;
    }

    .chat-container {
      height: calc(100vh - 350px);
      min-height: 300px;
      overflow-y: auto;
    }
  </style>
</head>

<body class="bg-gray-100">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white no-print">
      <div class="p-4">
        <h2 class="text-2xl font-semibold text-center mb-6">Admin Panel</h2>
        <nav>
          <ul class="space-y-2">
            <li>
              <a href="admin_dashboard.php" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=users" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-users mr-3"></i> Users
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=visualization" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-sitemap mr-3"></i> Tree Visualization
              </a>
            </li>
            <li>
              <a href="admin_full_tree.php" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-project-diagram mr-3"></i> Full Tree View
              </a>
            </li>
            <li>
              <a href="admin_messages.php" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-comments mr-3"></i> Messages
                <?php if ($unreadCount > 0): ?>
                  <span class="ml-auto bg-red-500 text-white px-2 py-1 rounded-full text-xs"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=commission-rates" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-sliders-h mr-3"></i> Commission Rates
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=top-referrers" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-trophy mr-3"></i> Top Referrers
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=recent-commissions" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-history mr-3"></i> Recent Commissions
              </a>
            </li>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
              <li>
                <a href="admin_dashboard.php?tab=admins" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                  <i class="fas fa-user-shield mr-3"></i> Admins
                </a>
              </li>
            <?php endif; ?>
            <li class="mt-10">
              <a href="admin_logout.php" class="flex items-center p-3 text-white hover:bg-red-700 rounded-lg group">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 overflow-x-hidden">
      <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-4 px-6 flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-900">Messages</h1>

          <div class="flex items-center space-x-4">
            <a href="admin_messages.php" class="relative">
              <i class="fas fa-refresh text-xl text-gray-600 hover:text-gray-900"></i>
              <?php if ($unreadCount > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white px-1.5 py-0.5 rounded-full text-xs"><?php echo $unreadCount; ?></span>
              <?php endif; ?>
            </a>
            <span class="text-gray-600">
              Welcome, <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
            </span>
          </div>
        </div>
      </header>

      <main class="max-w-7xl mx-auto py-6 px-6">
        <!-- Alerts for messages and errors -->
        <?php if (!empty($message)): ?>
          <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg alert">
            <div class="flex justify-between items-center">
              <span><?php echo $message; ?></span>
              <button type="button" class="close-alert text-green-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg alert">
            <div class="flex justify-between items-center">
              <span><?php echo $error; ?></span>
              <button type="button" class="close-alert text-red-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row gap-6">
          <!-- User List -->
          <div class="md:w-1/3 bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-users mr-2"></i> Top Commission Earners
              </h3>
              <p class="text-sm text-gray-600 mt-1">Click on a user to start a conversation</p>
            </div>
            <div class="overflow-y-auto" style="max-height: calc(100vh - 200px);">
              <ul class="divide-y divide-gray-200">
                <?php foreach ($topEarners as $user): ?>
                  <li>
                    <a href="?user_id=<?php echo $user['id']; ?>" class="block hover:bg-gray-50 transition-colors <?php echo ($selectedUserId == $user['id']) ? 'bg-blue-50' : ''; ?>">
                      <div class="flex items-center px-4 py-3">
                        <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                          <span class="text-blue-600 font-semibold">
                            <?php echo substr($user['full_name'], 0, 1); ?>
                          </span>
                        </div>
                        <div class="ml-3 flex-1">
                          <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <?php if ($user['unread_count'] > 0): ?>
                              <span class="bg-red-500 text-white px-2 py-1 rounded-full text-xs"><?php echo $user['unread_count']; ?></span>
                            <?php endif; ?>
                          </div>
                          <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                      </div>
                    </a>
                  </li>
                <?php endforeach; ?>

                <?php if (empty($topEarners)): ?>
                  <li class="px-4 py-5 text-center text-gray-500">
                    No users available
                  </li>
                <?php endif; ?>
              </ul>
            </div>
          </div>

          <!-- Chat Section -->
          <div class="md:w-2/3 bg-white rounded-lg shadow-lg overflow-hidden">
            <?php if ($userInfo): ?>
              <!-- User Info -->
              <div class="p-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-blue-600 font-semibold text-lg">
                      <?php echo substr($userInfo['full_name'], 0, 1); ?>
                    </span>
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($userInfo['full_name']); ?></h4>
                    <div class="flex flex-wrap text-xs text-gray-500 gap-3">
                      <span><i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($userInfo['email']); ?></span>
                      <?php if (!empty($userInfo['phone'])): ?>
                        <span><i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($userInfo['phone']); ?></span>
                      <?php endif; ?>
                      <span><i class="fas fa-key mr-1"></i> <?php echo htmlspecialchars($userInfo['referral_code']); ?></span>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-semibold text-green-600">$<?php echo number_format($userInfo['total_earnings'], 2); ?></div>
                    <div class="text-xs text-gray-500"><?php echo number_format($userInfo['referral_count']); ?> referrals</div>
                  </div>
                </div>
                <div class="mt-2 flex gap-2 justify-end">
                  <a href="admin_user_tree.php?id=<?php echo $userInfo['id']; ?>" class="text-xs text-blue-600 hover:text-blue-900">
                    <i class="fas fa-sitemap mr-1"></i> View Tree
                  </a>
                  <a href="admin_full_tree.php?id=<?php echo $userInfo['id']; ?>" class="text-xs text-purple-600 hover:text-purple-900">
                    <i class="fas fa-project-diagram mr-1"></i> View Full Tree
                  </a>
                </div>
              </div>

              <!-- Chat Messages -->
              <div class="chat-container p-4" id="chat-container">
                <?php foreach ($chatHistory as $chat): ?>
                  <div class="flex flex-col <?php echo ($chat['sent_by'] == 'admin') ? 'items-end' : 'items-start'; ?>">
                    <div class="chat-bubble <?php echo ($chat['sent_by'] == 'admin') ? 'chat-bubble-admin' : 'chat-bubble-user'; ?>">
                      <?php echo nl2br(htmlspecialchars($chat['message'])); ?>
                    </div>
                    <div class="text-xs text-gray-500 <?php echo ($chat['sent_by'] == 'admin') ? 'text-right' : 'text-left'; ?> mb-4">
                      <?php echo date('M d, Y H:i', strtotime($chat['created_at'])); ?>
                    </div>
                  </div>
                <?php endforeach; ?>

                <?php if (empty($chatHistory)): ?>
                  <div class="flex items-center justify-center h-full">
                    <div class="text-center text-gray-500">
                      <i class="fas fa-comments text-4xl mb-2"></i>
                      <p>No messages yet. Start the conversation!</p>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Message Input -->
              <div class="p-4 border-t border-gray-200">
                <form action="" method="POST">
                  <input type="hidden" name="user_id" value="<?php echo $userInfo['id']; ?>">
                  <div class="flex gap-2">
                    <textarea name="message_text" class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="2" placeholder="Type your message here..." required></textarea>
                    <button type="submit" name="send_message" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                      <i class="fas fa-paper-plane mr-2"></i> Send
                    </button>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <div class="flex items-center justify-center h-64">
                <div class="text-center text-gray-500">
                  <i class="fas fa-comments text-5xl mb-3"></i>
                  <p>Select a user to start messaging</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Auto-scroll to bottom of chat
    document.addEventListener('DOMContentLoaded', function() {
      const chatContainer = document.getElementById('chat-container');
      if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
      }

      // Close alerts
      const closeButtons = document.querySelectorAll('.close-alert');
      closeButtons.forEach(button => {
        button.addEventListener('click', function() {
          this.closest('.alert').remove();
        });
      });
    });
  </script>
</body>

</html>