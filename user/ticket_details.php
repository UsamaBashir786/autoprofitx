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
$ticket = null;
$responses = [];

// Check if ticket ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header("Location: support.php");
  exit();
}

$ticket_id = intval($_GET['id']);

// Fetch ticket details
$ticket_sql = "SELECT * FROM support_tickets WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($ticket_sql);
$stmt->bind_param("ii", $ticket_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // Ticket not found or does not belong to this user
  header("Location: support.php");
  exit();
}

$ticket = $result->fetch_assoc();
$stmt->close();

// Process reply submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reply'])) {
  $reply_message = trim($_POST['reply_message']);
  
  if (empty($reply_message)) {
    $error_message = "Please enter a message";
  } else {
    // Check if support_responses table exists
    $check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_responses'";
    $check_result = $conn->query($check_table_sql);
    $table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);
    
    if (!$table_exists) {
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
    
    // Insert reply
    $insert_sql = "INSERT INTO support_responses (ticket_id, user_id, message) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iis", $ticket_id, $user_id, $reply_message);
    
    if ($stmt->execute()) {
      // Update ticket status to open if it was resolved or closed
      if ($ticket['status'] == 'resolved' || $ticket['status'] == 'closed') {
        $update_status_sql = "UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_status_sql);
        $update_stmt->bind_param("i", $ticket_id);
        $update_stmt->execute();
        $update_stmt->close();
        $ticket['status'] = 'open'; // Update local status
      }
      
      $success_message = "Your reply has been submitted successfully";
    } else {
      $error_message = "Error submitting your reply. Please try again.";
    }
    
    $stmt->close();
  }
}

// Fetch user email or full_name for display purposes
$user_info_sql = "SELECT email, full_name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_info_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();
$user_display_name = $user_info['full_name'] ?? $user_info['email'] ?? 'User';
$user_stmt->close();

// Fetch ticket responses
// Modified query to use id, full_name, or email instead of username
$responses_sql = "SELECT r.*, 
                 u.email as user_email,
                 u.full_name as user_full_name, 
                 a.name as admin_name,
                 a.username as admin_username 
                 FROM support_responses r
                 LEFT JOIN users u ON r.user_id = u.id
                 LEFT JOIN admin_users a ON r.admin_id = a.id
                 WHERE r.ticket_id = ?
                 ORDER BY r.created_at ASC";
                 
$stmt = $conn->prepare($responses_sql);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$responses_result = $stmt->get_result();

while ($row = $responses_result->fetch_assoc()) {
  $responses[] = $row;
}
$stmt->close();

// Handle ticket status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['close_ticket'])) {
  $update_sql = "UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = ? AND user_id = ?";
  $stmt = $conn->prepare($update_sql);
  $stmt->bind_param("ii", $ticket_id, $user_id);
  
  if ($stmt->execute()) {
    $ticket['status'] = 'closed'; // Update local status
    $success_message = "Ticket has been closed successfully";
  } else {
    $error_message = "Error closing the ticket. Please try again.";
  }
  
  $stmt->close();
}

// Status display settings
$status_classes = [
  'open' => 'bg-blue-900 text-blue-200',
  'in_progress' => 'bg-yellow-900 text-yellow-200',
  'resolved' => 'bg-green-900 text-green-200',
  'closed' => 'bg-gray-700 text-gray-300'
];
$status_text = ucfirst(str_replace('_', ' ', $ticket['status']));
$status_class = $status_classes[$ticket['status']] ?? 'bg-gray-700 text-gray-300';

// Priority display settings
$priority_classes = [
  'low' => 'text-green-400',
  'medium' => 'text-yellow-400',
  'high' => 'text-red-400'
];
$priority_text = ucfirst($ticket['priority']);
$priority_class = $priority_classes[$ticket['priority']] ?? 'text-gray-400';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_id']); ?> - AutoProftX Support</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
          <div class="flex items-center">
            <a href="support.php" class="text-gray-400 hover:text-yellow-500 mr-2">
              <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold">Support Ticket</h1>
          </div>
          <p class="text-gray-400">Ticket ID: <?php echo htmlspecialchars($ticket['ticket_id']); ?></p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-2">
          <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
            <?php echo $status_text; ?>
          </span>
          <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-800 <?php echo $priority_class; ?>">
            <?php echo $priority_text; ?> Priority
          </span>
        </div>
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

      <!-- Ticket Details -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-700">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <h2 class="text-xl font-bold"><?php echo htmlspecialchars($ticket['subject']); ?></h2>
            <div class="mt-2 md:mt-0 text-sm text-gray-400">
              <?php echo date('F j, Y · g:i A', strtotime($ticket['created_at'])); ?>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">
              <?php echo ucfirst(htmlspecialchars($ticket['category'])); ?>
            </span>
          </div>
        </div>
        <div class="p-6 bg-gray-750">
          <div class="prose prose-dark max-w-none text-gray-300">
            <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
          </div>
        </div>
      </div>

      <!-- Conversation Thread -->
      <h3 class="text-xl font-bold mb-4">Conversation</h3>
      
      <div class="space-y-4 mb-8">
        <?php if (empty($responses)): ?>
          <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 text-center">
            <p class="text-gray-400">No responses yet. Add a reply below.</p>
          </div>
        <?php else: ?>
          <?php foreach ($responses as $response): ?>
            <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
              <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <div class="flex items-center">
                  <?php if ($response['admin_id']): ?>
                    <div class="bg-yellow-500 text-black h-8 w-8 rounded-full flex items-center justify-center font-bold mr-3">
                      <i class="fas fa-headset"></i>
                    </div>
                    <div>
                      <div class="font-medium">
                        <?php echo htmlspecialchars($response['admin_name'] ?? $response['admin_username'] ?? 'Support Agent'); ?>
                      </div>
                      <div class="text-xs text-yellow-500">Support Team</div>
                    </div>
                  <?php else: ?>
                    <div class="bg-blue-600 h-8 w-8 rounded-full flex items-center justify-center font-bold mr-3">
                      <i class="fas fa-user"></i>
                    </div>
                    <div>
                      <div class="font-medium">You</div>
                      <div class="text-xs text-gray-400">
                        <?php echo htmlspecialchars($response['user_full_name'] ?? $response['user_email'] ?? $user_display_name); ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="text-sm text-gray-400">
                  <?php echo date('M j, Y · g:i A', strtotime($response['created_at'])); ?>
                </div>
              </div>
              <div class="p-4">
                <div class="prose prose-dark max-w-none text-gray-300">
                  <?php echo nl2br(htmlspecialchars($response['message'])); ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Reply Form -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-8">
        <div class="p-4 border-b border-gray-700">
          <h3 class="font-bold">Add Reply</h3>
        </div>
        <div class="p-6">
          <?php if ($ticket['status'] === 'closed'): ?>
            <div class="bg-gray-700 rounded-lg p-4 text-center">
              <p class="text-gray-300">This ticket is closed. You can't add new replies.</p>
              <p class="text-sm text-gray-400 mt-2">If you need further assistance, please open a new ticket.</p>
            </div>
          <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $ticket_id; ?>" method="POST">
              <div class="space-y-4">
                <div>
                  <label for="reply_message" class="block text-sm font-medium text-gray-300 mb-1">Your Reply</label>
                  <textarea id="reply_message" name="reply_message" rows="4" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required></textarea>
                </div>
                
                <div class="flex justify-between items-center">
                  <button type="submit" name="submit_reply" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-md transition duration-300">
                    <i class="fas fa-paper-plane mr-2"></i> Send Reply
                  </button>
                  
                  <?php if ($ticket['status'] !== 'closed'): ?>
                    <button type="submit" name="close_ticket" onclick="return confirm('Are you sure you want to close this ticket?');" class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-md transition duration-300">
                      <i class="fas fa-times-circle mr-2"></i> Close Ticket
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Ticket Information -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-700">
          <h3 class="font-bold">Ticket Information</h3>
        </div>
        <div class="p-4">
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
            <div class="sm:col-span-2">
              <dt class="text-sm font-medium text-gray-400">Ticket ID</dt>
              <dd class="mt-1 text-sm text-white"><?php echo htmlspecialchars($ticket['ticket_id']); ?></dd>
            </div>
            
            <div>
              <dt class="text-sm font-medium text-gray-400">Status</dt>
              <dd class="mt-1 text-sm">
                <span class="px-2 py-1 text-xs rounded-full <?php echo $status_class; ?>">
                  <?php echo $status_text; ?>
                </span>
              </dd>
            </div>
            
            <div>
              <dt class="text-sm font-medium text-gray-400">Priority</dt>
              <dd class="mt-1 text-sm font-medium <?php echo $priority_class; ?>">
                <?php echo $priority_text; ?>
              </dd>
            </div>
            
            <div>
              <dt class="text-sm font-medium text-gray-400">Category</dt>
              <dd class="mt-1 text-sm text-white"><?php echo ucfirst(htmlspecialchars($ticket['category'])); ?></dd>
            </div>
            
            <div>
              <dt class="text-sm font-medium text-gray-400">Created</dt>
              <dd class="mt-1 text-sm text-white"><?php echo date('F j, Y · g:i A', strtotime($ticket['created_at'])); ?></dd>
            </div>
            
            <div>
              <dt class="text-sm font-medium text-gray-400">Last Updated</dt>
              <dd class="mt-1 text-sm text-white"><?php echo date('F j, Y · g:i A', strtotime($ticket['updated_at'])); ?></dd>
            </div>
            
            <div>
              <dt class="text-sm font-medium text-gray-400">Response Time</dt>
              <dd class="mt-1 text-sm text-white">
                <?php
                  $first_admin_response = null;
                  foreach ($responses as $response) {
                    if (!empty($response['admin_id'])) {
                      $first_admin_response = $response;
                      break;
                    }
                  }
                  
                  if ($first_admin_response) {
                    $created = new DateTime($ticket['created_at']);
                    $responded = new DateTime($first_admin_response['created_at']);
                    $diff = $created->diff($responded);
                    
                    if ($diff->days > 0) {
                      echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ', ';
                    }
                    
                    echo $diff->h . ' hour' . ($diff->h != 1 ? 's' : '') . ', ';
                    echo $diff->i . ' minute' . ($diff->i != 1 ? 's' : '');
                  } else {
                    echo 'Awaiting response';
                  }
                ?>
              </dd>
            </div>
          </dl>
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
    });
  </script>
</body>

</html>