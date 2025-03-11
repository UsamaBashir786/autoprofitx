<?php
// Include database connection
include '../config/db.php';

// Check user authentication
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

$userId = $_SESSION['user_id'];

// Get chat history
function getChatHistory($userId, $conn)
{
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

// Mark messages as read
function markMessagesAsRead($userId, $conn)
{
  try {
    $updateStmt = $conn->prepare("
      UPDATE admin_messages 
      SET `read` = 1 
      WHERE user_id = ? AND sent_by = 'admin' AND `read` = 0
    ");
    $updateStmt->bind_param("i", $userId);
    $updateStmt->execute();
    return true;
  } catch (Exception $e) {
    error_log("Error marking messages as read: " . $e->getMessage());
    return false;
  }
}

// Get the chat history
$chatHistory = getChatHistory($userId, $conn);

// Mark messages as read
markMessagesAsRead($userId, $conn);

// Generate HTML for the chat messages
$html = '';

if (empty($chatHistory)) {
  $html = '
    <div class="flex items-center justify-center h-full">
      <div class="text-center text-gray-500">
        <i class="fas fa-comments text-5xl mb-4"></i>
        <p class="text-xl font-semibold mb-2">Welcome to Support</p>
        <p class="max-w-md mx-auto mb-4">How can we help you today? Send us a message and our team will assist you as soon as possible.</p>
        <div class="flex flex-wrap justify-center gap-4 text-sm">
          <div class="bg-gray-700 p-3 rounded-lg">
            <i class="fas fa-question-circle text-blue-400 mr-2"></i> Account Issues
          </div>
          <div class="bg-gray-700 p-3 rounded-lg">
            <i class="fas fa-dollar-sign text-green-400 mr-2"></i> Payment Help
          </div>
          <div class="bg-gray-700 p-3 rounded-lg">
            <i class="fas fa-user-plus text-yellow-400 mr-2"></i> Referral Program
          </div>
        </div>
      </div>
    </div>
  ';
} else {
  foreach ($chatHistory as $chat) {
    $align = ($chat['sent_by'] == 'user') ? 'items-end' : 'items-start';
    $bubbleClass = ($chat['sent_by'] == 'user') ? 'chat-bubble-user' : 'chat-bubble-admin';
    $textAlign = ($chat['sent_by'] == 'user') ? 'text-right' : 'text-left';
    
    $html .= '
      <div class="flex flex-col ' . $align . '">
        <div class="chat-bubble ' . $bubbleClass . '">
          ' . nl2br(htmlspecialchars($chat['message'])) . '
        </div>
        <div class="text-xs text-gray-500 ' . $textAlign . ' mb-4">
          ' . date('M d, Y H:i', strtotime($chat['created_at'])) . '
        </div>
      </div>
    ';
  }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'html' => $html
]);
exit();