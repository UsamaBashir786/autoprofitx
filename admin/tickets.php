<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}

// Initialize variables
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$success_message = "";
$error_message = "";
$ticket = null;
$responses = [];

// Check if ticket ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header("Location: tickets.php");
  exit();
}

$ticket_id = intval($_GET['id']);

// Fetch ticket details
$ticket_sql = "SELECT t.*, u.full_name as user_name, u.email as user_email 
               FROM support_tickets t
               LEFT JOIN users u ON t.user_id = u.id
               WHERE t.id = ?";
$stmt = $conn->prepare($ticket_sql);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // Ticket not found
  header("Location: tickets.php?error=not_found");
  exit();
}

$ticket = $result->fetch_assoc();
$stmt->close();

// Process admin reply submission
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
    
    // Insert admin reply
    $insert_sql = "INSERT INTO support_responses (ticket_id, admin_id, message) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iis", $ticket_id, $admin_id, $reply_message);
    
    if ($stmt->execute()) {
      // Update ticket status to in_progress if it was open
      if ($ticket['status'] == 'open') {
        $update_status_sql = "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_status_sql);
        $update_stmt->bind_param("i", $ticket_id);
        $update_stmt->execute();
        $update_stmt->close();
        $ticket['status'] = 'in_progress'; // Update local status
      }
      
      $success_message = "Your reply has been submitted successfully";
    } else {
      $error_message = "Error submitting your reply. Please try again.";
    }
    
    $stmt->close();
  }
}

// Handle ticket status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
  $new_status = $_POST['status'];
  
  // Validate status
  $valid_statuses = ['open', 'in_progress', 'resolved', 'closed'];
  if (in_array($new_status, $valid_statuses)) {
    $update_sql = "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $new_status, $ticket_id);
    
    if ($stmt->execute()) {
      $ticket['status'] = $new_status; // Update local status
      $success_message = "Ticket status updated to " . ucfirst(str_replace('_', ' ', $new_status));
    } else {
      $error_message = "Error updating ticket status. Please try again.";
    }
    
    $stmt->close();
  } else {
    $error_message = "Invalid status selected";
  }
}

// Send email notification to user (when admin responds)
function sendEmailNotification($user_email, $ticket_id, $ticket_subject) {
  // This is a placeholder function - implement your email sending logic here
  // You can use PHP's mail() function or a library like PHPMailer
  
  $subject = "Update on your support ticket #" . $ticket_id;
  $message = "Hello,\n\nYour support ticket regarding \"" . $ticket_subject . "\" has been updated with a new response from our support team.\n\nPlease log in to your account to view the response and reply if needed.\n\nThank you,\nThe AutoProftX Support Team";
  
  // Uncomment and adapt this to actually send emails
  // mail($user_email, $subject, $message, "From: support@autoproftx.com");
}

// Fetch all responses
$responses_sql = "SELECT r.*, 
                 u.full_name as user_name, 
                 u.email as user_email,
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

// Status display settings
$status_classes = [
  'open' => 'bg-blue-100 text-blue-800',
  'in_progress' => 'bg-yellow-100 text-yellow-800',
  'resolved' => 'bg-green-100 text-green-800',
  'closed' => 'bg-gray-100 text-gray-800'
];

$status_text = ucfirst(str_replace('_', ' ', $ticket['status']));
$status_class = $status_classes[$ticket['status']] ?? 'bg-gray-100 text-gray-800';

// Priority display settings
$priority_classes = [
  'low' => 'bg-green-100 text-green-800',
  'medium' => 'bg-yellow-100 text-yellow-800',
  'high' => 'bg-red-100 text-red-800'
];

$priority_text = ucfirst($ticket['priority']);
$priority_class = $priority_classes[$ticket['priority']] ?? 'bg-gray-100 text-gray-800';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_id']); ?> - AutoProftX Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .prose p {
      margin-top: 1em;
      margin-bottom: 1em;
    }

    /* Custom Badge Colors */
    .bg-blue-100 {
      background-color: rgba(59, 130, 246, 0.2);
    }
    .text-blue-800 {
      color: #1e40af;
    }
    .bg-yellow-100 {
      background-color: rgba(234, 179, 8, 0.2);
    }
    .text-yellow-800 {
      color: #854d0e;
    }
    .bg-green-100 {
      background-color: rgba(34, 197, 94, 0.2);
    }
    .text-green-800 {
      color: #166534;
    }
    .bg-red-100 {
      background-color: rgba(239, 68, 68, 0.2);
    }
    .text-red-800 {
      color: #991b1b;
    }
    .bg-gray-100 {
      background-color: rgba(156, 163, 175, 0.2);
    }
    .text-gray-800 {
      color: #1f2937;
    }
  </style>
</head>

<body class="bg-gray-900 text-white min-h-screen flex flex-col">
  <!-- Header / Navbar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-grow container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <div class="flex items-center">
          <a href="tickets.php" class="text-gray-400 hover:text-yellow-500 mr-3">
            <i class="fas fa-arrow-left"></i>
          </a>
          <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
        </div>
        <p class="text-gray-400">Ticket ID: <?php echo htmlspecialchars($ticket['ticket_id']); ?></p>
      </div>
      
      <div class="flex space-x-2">
        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
          <?php echo $status_text; ?>
        </span>
        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $priority_class; ?>">
          <?php echo $priority_text; ?> Priority
        </span>
      </div>
    </div>

    <?php if (!empty($success_message) || isset($_GET['success'])): ?>
      <?php 
        if (isset($_GET['success'])) {
          if ($_GET['success'] == 'reply') {
            $success_message = "Your reply has been submitted successfully";
          } elseif ($_GET['success'] == 'status') {
            $success_message = "Ticket status updated successfully";
          }
        }
      ?>
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

    <!-- Ticket Detail View -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main ticket content and responses -->
      <div class="lg:col-span-2">
        <!-- Original Ticket -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-6">
          <div class="p-4 border-b border-gray-700 flex justify-between items-center">
            <div class="flex items-center">
              <div class="bg-blue-600 h-8 w-8 rounded-full flex items-center justify-center font-bold mr-3">
                <i class="fas fa-user"></i>
              </div>
              <div>
                <div class="font-medium"><?php echo htmlspecialchars($ticket['user_name'] ?? 'User'); ?></div>
                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($ticket['user_email'] ?? ''); ?></div>
              </div>
            </div>
            <div class="text-sm text-gray-400">
              <?php echo date('M j, Y · g:i A', strtotime($ticket['created_at'])); ?>
            </div>
          </div>
          <div class="p-4">
            <div class="prose prose-dark max-w-none text-gray-300">
              <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
            </div>
          </div>
        </div>

        <!-- Responses Thread -->
        <?php if (!empty($responses)): ?>
          <h3 class="text-xl font-bold mb-4">Conversation</h3>
          <div class="space-y-4 mb-6">
            <?php foreach ($responses as $response): ?>
              <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                  <div class="flex items-center">
                    <?php if ($response['admin_id']): ?>
                      <div class="bg-yellow-500 text-black h-8 w-8 rounded-full flex items-center justify-center font-bold mr-3">
                        <i class="fas fa-headset"></i>
                      </div>
                      <div>
                        <div class="font-medium"><?php echo htmlspecialchars($response['admin_name'] ?? $response['admin_username'] ?? 'Support Team'); ?></div>
                        <div class="text-xs text-yellow-500">Support Team</div>
                      </div>
                    <?php else: ?>
                      <div class="bg-blue-600 h-8 w-8 rounded-full flex items-center justify-center font-bold mr-3">
                        <i class="fas fa-user"></i>
                      </div>
                      <div>
                        <div class="font-medium"><?php echo htmlspecialchars($response['user_name'] ?? 'User'); ?></div>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($response['user_email'] ?? ''); ?></div>
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
          </div>
        <?php endif; ?>

        <!-- Reply Form -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-6">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-bold">Post Reply</h3>
          </div>
          <div class="p-6">
            <?php if ($ticket['status'] === 'closed'): ?>
              <div class="bg-gray-700 rounded-lg p-4 text-center">
                <p class="text-gray-300">This ticket is closed. You can reopen it by changing the status.</p>
              </div>
            <?php else: ?>
              <form action="ticket-details.php?id=<?php echo $ticket_id; ?>" method="POST">
                <div class="space-y-4">
                  <div>
                    <label for="reply_message" class="block text-sm font-medium text-gray-300 mb-1">Your Response</label>
                    <textarea id="reply_message" name="reply_message" rows="4" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required></textarea>
                  </div>
                  
                  <div>
                    <button type="submit" name="submit_reply" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-md transition duration-300">
                      <i class="fas fa-paper-plane mr-2"></i> Send Response
                    </button>
                  </div>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Ticket Info Sidebar -->
      <div class="lg:col-span-1">
        <!-- Ticket Status Management -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-6">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-bold">Ticket Management</h3>
          </div>
          <div class="p-4">
            <form action="ticket-details.php?id=<?php echo $ticket_id; ?>" method="POST">
              <div class="space-y-4">
                <div>
                  <label for="status" class="block text-sm font-medium text-gray-300 mb-1">Change Status</label>
                  <select id="status" name="status" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                    <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                  </select>
                </div>
                
                <div>
                  <button type="submit" name="update_status" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-md w-full transition duration-300">
                    Update Status
                  </button>
                </div>
              </div>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-700">
              <div class="flex justify-between mb-2">
                <button class="text-yellow-500 hover:text-yellow-400 text-sm flex items-center" onclick="window.print()">
                  <i class="fas fa-print mr-1"></i> Print Ticket
                </button>
                
                <?php if ($ticket['status'] != 'closed'): ?>
                  <form action="ticket-details.php?id=<?php echo $ticket_id; ?>" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to close this ticket?');">
                    <input type="hidden" name="status" value="closed">
                    <button type="submit" name="update_status" class="text-red-400 hover:text-red-300 text-sm flex items-center">
                      <i class="fas fa-times-circle mr-1"></i> Close Ticket
                    </button>
                  </form>
                <?php else: ?>
                  <form action="ticket-details.php?id=<?php echo $ticket_id; ?>" method="POST" class="inline">
                    <input type="hidden" name="status" value="open">
                    <button type="submit" name="update_status" class="text-green-400 hover:text-green-300 text-sm flex items-center">
                      <i class="fas fa-redo-alt mr-1"></i> Reopen Ticket
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Ticket Details -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-6">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-bold">Ticket Information</h3>
          </div>
          <div class="p-4">
            <dl class="space-y-4">
              <div>
                <dt class="text-sm font-medium text-gray-400">Ticket ID</dt>
                <dd class="mt-1 text-sm text-white"><?php echo htmlspecialchars($ticket['ticket_id']); ?></dd>
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
                      echo '<span class="text-yellow-500">Not yet responded</span>';
                    }
                  ?>
                </dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- User Information -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-bold">User Information</h3>
          </div>
          <div class="p-4">
            <dl class="space-y-4">
              <div>
                <dt class="text-sm font-medium text-gray-400">Name</dt>
                <dd class="mt-1 text-sm text-white"><?php echo htmlspecialchars($ticket['user_name'] ?? 'N/A'); ?></dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-400">Email</dt>
                <dd class="mt-1 text-sm text-white break-all">
                  <a href="mailto:<?php echo htmlspecialchars($ticket['user_email'] ?? ''); ?>" class="text-yellow-500 hover:underline">
                    <?php echo htmlspecialchars($ticket['user_email'] ?? 'N/A'); ?>
                  </a>
                </dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-400">User ID</dt>
                <dd class="mt-1 text-sm text-white"><?php echo htmlspecialchars($ticket['user_id'] ?? 'N/A'); ?></dd>
              </div>
              
              <div class="pt-4">
                <a href="users.php?id=<?php echo $ticket['user_id']; ?>" class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-md w-full flex justify-center items-center transition duration-300">
                  <i class="fas fa-user mr-2"></i> View User Profile
                </a>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php include 'includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Focus the reply textarea when the page loads if it's not closed
      const replyTextarea = document.getElementById('reply_message');
      const ticketStatus = '<?php echo $ticket['status']; ?>';
      
      if (replyTextarea && ticketStatus !== 'closed' && window.location.href.includes('success=')) {
        // If we just submitted something, scroll to the latest response
        const responses = document.querySelectorAll('.space-y-4 > .bg-gray-800');
        if (responses.length > 0) {
          const lastResponse = responses[responses.length - 1];
          lastResponse.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
      
      // Validate reply form
      const replyForm = document.querySelector('form');
      if (replyForm) {
        replyForm.addEventListener('submit', function(e) {
          const textarea = document.getElementById('reply_message');
          if (textarea && textarea.value.trim() === '') {
            e.preventDefault();
            textarea.focus();
            alert('Please enter a reply message');
          }
        });
      }
    });
  </script>
</body>
</html>