<?php
// Start session
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Initialize variables
$success_message = "";
$error_message = "";
$tickets = [];
$ticket = null;
$responses = [];
$view_mode = 'list'; // Default view - can be 'list' or 'detail'

// Check if support_tickets table exists
$check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_tickets'";
$check_result = $conn->query($check_table_sql);
$table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

if (!$table_exists) {
  $error_message = "No support tickets found. The system may not be configured for support tickets yet.";
} else {
  // Check if viewing a specific ticket
  if (isset($_GET['id']) && !empty($_GET['id'])) {
    $ticket_id = intval($_GET['id']);
    $view_mode = 'detail';

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
      $error_message = "Ticket not found";
      $view_mode = 'list';
    } else {
      $ticket = $result->fetch_assoc();

      // Fetch all responses for this ticket
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
    }
  }

  // Process admin reply submission
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reply']) && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $reply_message = trim($_POST['reply_message']);

    if (empty($reply_message)) {
      $error_message = "Please enter a message";
    } else {
      // Check if support_responses table exists
      $check_responses_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'support_responses'";
      $check_result = $conn->query($check_responses_table_sql);
      $responses_table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

      if (!$responses_table_exists) {
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
        $check_status_sql = "SELECT status FROM support_tickets WHERE id = ?";
        $check_stmt = $conn->prepare($check_status_sql);
        $check_stmt->bind_param("i", $ticket_id);
        $check_stmt->execute();
        $status_result = $check_stmt->get_result();
        $current_status = $status_result->fetch_assoc()['status'];
        $check_stmt->close();

        if ($current_status == 'open') {
          $update_status_sql = "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?";
          $update_stmt = $conn->prepare($update_status_sql);
          $update_stmt->bind_param("i", $ticket_id);
          $update_stmt->execute();
          $update_stmt->close();
        }

        $success_message = "Your reply has been submitted successfully";

        // Refresh the page to show the new reply
        header("Location: ticket.php?id=$ticket_id&success=reply");
        exit();
      } else {
        $error_message = "Error submitting your reply: " . $stmt->error;
      }

      $stmt->close();
    }
  }

  // Handle ticket status update
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status']) && isset($_POST['ticket_id']) && isset($_POST['status'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $new_status = $_POST['status'];

    // Validate status
    $valid_statuses = ['open', 'in_progress', 'resolved', 'closed'];
    if (in_array($new_status, $valid_statuses)) {
      $update_sql = "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
      $stmt = $conn->prepare($update_sql);
      $stmt->bind_param("si", $new_status, $ticket_id);

      if ($stmt->execute()) {
        $success_message = "Ticket status updated to " . ucfirst(str_replace('_', ' ', $new_status));

        // Refresh the page to show the new status
        header("Location: ticket.php?id=$ticket_id&success=status");
        exit();
      } else {
        $error_message = "Error updating ticket status: " . $stmt->error;
      }

      $stmt->close();
    } else {
      $error_message = "Invalid status selected";
    }
  }

  // If we're in list view or there was an error loading the ticket, fetch all tickets
  if ($view_mode == 'list' || !$ticket) {
    // Handle filter parameters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build the query
    $tickets_sql = "SELECT t.*, u.full_name as user_name, u.email as user_email, 
                   (SELECT COUNT(*) FROM support_responses WHERE ticket_id = t.id) as response_count
                   FROM support_tickets t
                   LEFT JOIN users u ON t.user_id = u.id
                   WHERE 1=1";

    $params = [];
    $param_types = "";

    if (!empty($status_filter) && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
      $tickets_sql .= " AND t.status = ?";
      $params[] = $status_filter;
      $param_types .= "s";
    }

    if (!empty($priority_filter) && in_array($priority_filter, ['low', 'medium', 'high'])) {
      $tickets_sql .= " AND t.priority = ?";
      $params[] = $priority_filter;
      $param_types .= "s";
    }

    if (!empty($search_query)) {
      $tickets_sql .= " AND (t.ticket_id LIKE ? OR t.subject LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
      $search_param = "%$search_query%";
      $params[] = $search_param;
      $params[] = $search_param;
      $params[] = $search_param;
      $params[] = $search_param;
      $param_types .= "ssss";
    }

    // Add sorting
    $tickets_sql .= " ORDER BY 
                     CASE 
                       WHEN t.status = 'open' THEN 1
                       WHEN t.status = 'in_progress' THEN 2
                       WHEN t.status = 'resolved' THEN 3
                       WHEN t.status = 'closed' THEN 4
                       ELSE 5
                     END,
                     CASE 
                       WHEN t.priority = 'high' THEN 1
                       WHEN t.priority = 'medium' THEN 2
                       WHEN t.priority = 'low' THEN 3
                       ELSE 4
                     END,
                     t.updated_at DESC";

    $stmt = $conn->prepare($tickets_sql);

    if (!empty($params)) {
      $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    $tickets_result = $stmt->get_result();

    while ($row = $tickets_result->fetch_assoc()) {
      $tickets[] = $row;
    }
    $stmt->close();
  }
}

// Helper function to get status badge HTML
function getStatusBadge($status)
{
  switch ($status) {
    case 'open':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-900 text-blue-200">Open</span>';
    case 'in_progress':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-900 text-yellow-200">In Progress</span>';
    case 'resolved':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-900 text-green-200">Resolved</span>';
    case 'closed':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-700 text-gray-300">Closed</span>';
    default:
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-700 text-gray-300">Unknown</span>';
  }
}

// Helper function to get priority badge HTML
function getPriorityBadge($priority)
{
  switch ($priority) {
    case 'high':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-900 text-red-200">High</span>';
    case 'medium':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-900 text-yellow-200">Medium</span>';
    case 'low':
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-900 text-green-200">Low</span>';
    default:
      return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-700 text-gray-300">Unknown</span>';
  }
}

// Set the title based on the view mode
$page_title = $view_mode == 'detail' ? "Ticket #" . ($ticket ? $ticket['ticket_id'] : '') : "Support Tickets";
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?> - AutoProftX</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #1f2937;
    }

    ::-webkit-scrollbar-thumb {
      background: #4b5563;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #f59e0b;
    }

    /* Active nav item */
    .nav-item.active {
      border-left: 3px solid #f59e0b;
      background-color: rgba(245, 158, 11, 0.1);
    }

    /* Card hover effect */
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    /* Gold gradient */
    .gold-gradient {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    /* Ticket row hover */
    .ticket-row:hover {
      background-color: rgba(255, 255, 255, 0.05);
    }

    /* Message container */
    .prose p {
      margin-top: 1em;
      margin-bottom: 1em;
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Top Navigation Bar -->
    <header class="bg-gray-800 border-b border-gray-700 shadow-md">
      <div class="flex items-center justify-between p-4">
        <!-- Mobile Menu Toggle -->
        <button id="mobile-menu-button" class="md:hidden text-gray-300 hover:text-white">
          <i class="fas fa-bars text-xl"></i>
        </button>

        <h1 class="text-xl font-bold text-white md:hidden">AutoProftX</h1>

        <!-- User Profile -->
        <div class="flex items-center space-x-4">
          <div class="relative">
            <button id="notifications-btn" class="text-gray-300 hover:text-white relative">
              <i class="fas fa-bell text-xl"></i>
              <?php if (!empty($tickets) && count(array_filter($tickets, function ($t) {
                return $t['status'] === 'open';
              })) > 0): ?>
                <span class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-red-500 flex items-center justify-center text-xs">
                  <?php echo count(array_filter($tickets, function ($t) {
                    return $t['status'] === 'open';
                  })); ?>
                </span>
              <?php endif; ?>
            </button>
          </div>

          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
              <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
            <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-90 z-50 md:hidden transform -translate-x-full transition-transform duration-300">
      <div class="flex flex-col overflow-y-scroll h-full bg-gray-800 w-64 py-8 px-6">
        <div class="flex justify-between items-center mb-8">
          <div class="flex items-center">
            <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
            <span class="text-xl font-bold text-yellow-500">AutoProftX</span>
          </div>
          <button id="close-sidebar" class="text-gray-300 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <nav class="space-y-2">
          <a href="index.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-tachometer-alt w-6"></i>
            <span>Dashboard</span>
          </a>
          <a href="users.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-users w-6"></i>
            <span>Users</span>
          </a>
          <a href="deposits.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-money-bill-wave w-6"></i>
            <span>Deposits</span>
          </a>
          <a href="ticket.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-headset w-6"></i>
            <span>Customer Support</span>
          </a>
          <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-line w-6"></i>
            <span>Investments</span>
          </a>
          <a href="staking.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-landmark w-6"></i>
            <span>Staking</span>
          </a>
          <a href="payment-methods.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-credit-card w-6"></i>
            <span>Payment Methods</span>
          </a>
          <a href="settings.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-cog w-6"></i>
            <span>Settings</span>
          </a>
        </nav>

        <div class="mt-auto">
          <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">
      <!-- Page Title -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <?php if ($view_mode == 'detail' && $ticket): ?>
            <div class="flex items-center">
              <a href="ticket.php" class="text-gray-400 hover:text-yellow-500 mr-3">
                <i class="fas fa-arrow-left"></i>
              </a>
              <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($ticket['subject']); ?></h2>
            </div>
            <p class="text-gray-400">Ticket ID: <?php echo htmlspecialchars($ticket['ticket_id']); ?></p>
          <?php else: ?>
            <h2 class="text-2xl font-bold">Support Tickets</h2>
            <p class="text-gray-400">Manage and respond to customer support tickets</p>
          <?php endif; ?>
        </div>

        <?php if ($view_mode == 'detail' && $ticket): ?>
          <div class="flex space-x-2">
            <?php
            $status_text = ucfirst(str_replace('_', ' ', $ticket['status']));
            $priority_text = ucfirst($ticket['priority']);

            $status_classes = [
              'open' => 'bg-blue-900 text-blue-200',
              'in_progress' => 'bg-yellow-900 text-yellow-200',
              'resolved' => 'bg-green-900 text-green-200',
              'closed' => 'bg-gray-700 text-gray-300'
            ];

            $priority_classes = [
              'low' => 'text-green-400',
              'medium' => 'text-yellow-400',
              'high' => 'text-red-400'
            ];

            $status_class = $status_classes[$ticket['status']] ?? 'bg-gray-700 text-gray-300';
            $priority_class = $priority_classes[$ticket['priority']] ?? 'text-gray-400';
            ?>
            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
              <?php echo $status_text; ?>
            </span>
            <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-800 <?php echo $priority_class; ?>">
              <?php echo $priority_text; ?> Priority
            </span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Success / Error Messages -->
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
        <div class="mb-6 bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md flex items-start">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="mb-6 bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md flex items-start">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if ($view_mode == 'list'): ?>
        <!-- Filter and Search Bar -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-4 mb-6">
          <form action="ticket.php" method="GET" class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
            <div class="flex-1">
              <input type="text" name="search" placeholder="Search tickets, users, or IDs..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-4 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
            </div>

            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
              <select name="status" class="bg-gray-700 border border-gray-600 rounded-md py-2 px-4 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                <option value="">All Statuses</option>
                <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] == 'open') ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                <option value="resolved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
              </select>

              <select name="priority" class="bg-gray-700 border border-gray-600 rounded-md py-2 px-4 text-white focus:outline-nonefocus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                <option value="">All Priorities</option>
                <option value="low" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                <option value="high" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
              </select>

              <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-6 rounded-md transition duration-200">
                <i class="fas fa-search mr-2"></i>Filter
              </button>
            </div>
          </form>
        </div>

        <!-- Tickets List -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
          <?php if (empty($tickets)): ?>
            <div class="p-8 text-center text-gray-400">
              <i class="fas fa-ticket-alt text-4xl mb-4"></i>
              <h3 class="text-xl font-semibold mb-2">No tickets found</h3>
              <p>There are no support tickets matching your search criteria.</p>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-gray-700">
                  <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ID</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Subject</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Priority</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Last Updated</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($tickets as $t): ?>
                    <tr class="ticket-row hover:bg-gray-750">
                      <td class="py-3 px-4 whitespace-nowrap">
                        <span class="font-mono text-sm"><?php echo htmlspecialchars($t['ticket_id']); ?></span>
                      </td>
                      <td class="py-3 px-4">
                        <a href="ticket.php?id=<?php echo $t['id']; ?>" class="font-medium text-white hover:text-yellow-500">
                          <?php echo htmlspecialchars($t['subject']); ?>
                        </a>
                        <?php if ($t['response_count'] > 0): ?>
                          <span class="ml-2 text-xs text-gray-400">(<?php echo $t['response_count']; ?> <?php echo $t['response_count'] == 1 ? 'reply' : 'replies'; ?>)</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3 px-4">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($t['user_name'] ?? 'U', 0, 1)); ?>
                          </div>
                          <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($t['user_name'] ?? 'Unknown'); ?></p>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($t['user_email'] ?? 'No email'); ?></p>
                          </div>
                        </div>
                      </td>
                      <td class="py-3 px-4">
                        <?php echo getStatusBadge($t['status']); ?>
                      </td>
                      <td class="py-3 px-4">
                        <?php echo getPriorityBadge($t['priority']); ?>
                      </td>
                      <td class="py-3 px-4 text-sm text-gray-400">
                        <?php
                        $updated_date = new DateTime($t['updated_at']);
                        $now = new DateTime();
                        $interval = $now->diff($updated_date);

                        if ($interval->y > 0) {
                          echo $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->m > 0) {
                          echo $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->d > 0) {
                          echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->h > 0) {
                          echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->i > 0) {
                          echo $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                        } else {
                          echo 'Just now';
                        }
                        ?>
                      </td>
                      <td class="py-3 px-4 whitespace-nowrap">
                        <a href="ticket.php?id=<?php echo $t['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-xs py-1 px-3 rounded-md mr-2">
                          <i class="fas fa-eye mr-1"></i>View
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <!-- Ticket Detail View -->
        <?php if ($ticket): ?>
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Ticket Messages Column -->
            <div class="lg:col-span-8">
              <!-- Ticket Message Thread -->
              <div class="bg-gray-800 rounded-lg border border-gray-700 p-5 mb-6">
                <!-- Original Message -->
                <div class="mb-6">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                      <div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold">
                        <?php echo strtoupper(substr($ticket['user_name'] ?? 'U', 0, 1)); ?>
                      </div>
                      <div class="ml-3">
                        <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($ticket['user_name'] ?? 'Unknown User'); ?></p>
                        <p class="text-xs text-gray-400">
                          <?php
                          $created_date = new DateTime($ticket['created_at']);
                          echo $created_date->format('M d, Y \a\t h:i A');
                          ?>
                        </p>
                      </div>
                    </div>
                    <div>
                      <span class="text-xs text-gray-400">Original Message</span>
                    </div>
                  </div>

                  <div class="prose prose-sm text-gray-300 ml-12 mt-2">
                    <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                      <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                    </div>

                    <?php if (!empty($ticket['attachment'])): ?>
                      <div class="mt-3">
                        <a href="../uploads/tickets/<?php echo htmlspecialchars($ticket['attachment']); ?>" target="_blank" class="flex items-center text-blue-400 hover:text-blue-300">
                          <i class="fas fa-paperclip mr-2"></i>
                          <?php echo htmlspecialchars($ticket['attachment']); ?>
                        </a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Responses -->
                <?php if (!empty($responses)): ?>
                  <?php foreach ($responses as $response): ?>
                    <?php
                    $is_admin = !empty($response['admin_id']);
                    $name = $is_admin ? ($response['admin_name'] ?? 'Admin') : ($response['user_name'] ?? 'User');
                    $color_class = $is_admin ? 'bg-yellow-600' : 'bg-gray-600';
                    ?>
                    <div class="mb-6">
                      <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                          <div class="h-10 w-10 rounded-full <?php echo $color_class; ?> flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($name, 0, 1)); ?>
                          </div>
                          <div class="ml-3">
                            <p class="text-sm font-medium text-white">
                              <?php echo htmlspecialchars($name); ?>
                              <?php if ($is_admin): ?>
                                <span class="text-xs bg-yellow-800 text-yellow-200 py-0.5 px-2 rounded-full ml-2">Support Team</span>
                              <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-400">
                              <?php
                              $response_date = new DateTime($response['created_at']);
                              echo $response_date->format('M d, Y \a\t h:i A');
                              ?>
                            </p>
                          </div>
                        </div>
                      </div>

                      <div class="prose prose-sm text-gray-300 ml-12 mt-2">
                        <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                          <?php echo nl2br(htmlspecialchars($response['message'])); ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- Admin Reply Form -->
                <?php if ($ticket['status'] != 'closed'): ?>
                  <div class="mt-8 border-t border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-white mb-4">Reply to this ticket</h3>
                    <form action="ticket.php" method="POST">
                      <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                      <div class="mb-4">
                        <textarea name="reply_message" rows="4" placeholder="Type your reply here..." class="bg-gray-700 border border-gray-600 rounded-md w-full py-3 px-4 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"></textarea>
                      </div>
                      <div class="flex justify-end">
                        <button type="submit" name="submit_reply" class="gold-gradient text-white py-2 px-6 rounded-md transition duration-200">
                          <i class="fas fa-paper-plane mr-2"></i>Send Reply
                        </button>
                      </div>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Ticket Information Column -->
            <div class="lg:col-span-4">
              <!-- Ticket Details Card -->
              <div class="bg-gray-800 rounded-lg border border-gray-700 p-5 mb-6">
                <h3 class="text-lg font-medium text-white mb-4">Ticket Information</h3>
                <div class="space-y-4">
                  <div>
                    <p class="text-sm text-gray-400 mb-1">Ticket ID</p>
                    <p class="font-mono"><?php echo htmlspecialchars($ticket['ticket_id']); ?></p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-400 mb-1">Created</p>
                    <p><?php
                        $created_date = new DateTime($ticket['created_at']);
                        echo $created_date->format('M d, Y \a\t h:i A');
                        ?></p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-400 mb-1">Last Updated</p>
                    <p><?php
                        $updated_date = new DateTime($ticket['updated_at']);
                        echo $updated_date->format('M d, Y \a\t h:i A');
                        ?></p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-400 mb-1">Category</p>
                    <p><?php echo htmlspecialchars(ucfirst($ticket['category'] ?? 'General')); ?></p>
                  </div>
                </div>

                <!-- Status Update Form -->
                <div class="mt-6 pt-6 border-t border-gray-700">
                  <h4 class="text-md font-medium text-white mb-4">Update Ticket Status</h4>
                  <form action="ticket.php" method="POST">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <div class="mb-4">
                      <select name="status" class="bg-gray-700 border border-gray-600 rounded-md py-2 px-4 text-white w-full focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                      </select>
                    </div>
                    <button type="submit" name="update_status" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition duration-200">
                      Update Status
                    </button>
                  </form>
                </div>
              </div>

              <!-- User Information Card -->
              <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                <h3 class="text-lg font-medium text-white mb-4">User Information</h3>
                <div class="flex items-center mb-4">
                  <div class="h-12 w-12 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold">
                    <?php echo strtoupper(substr($ticket['user_name'] ?? 'U', 0, 1)); ?>
                  </div>
                  <div class="ml-3">
                    <p class="font-medium text-white"><?php echo htmlspecialchars($ticket['user_name'] ?? 'Unknown User'); ?></p>
                    <p class="text-sm text-gray-400"><?php echo htmlspecialchars($ticket['user_email'] ?? 'No email'); ?></p>
                  </div>
                </div>

                <?php if (!empty($ticket['user_id'])): ?>
                  <a href="user-profile.php?id=<?php echo $ticket['user_id']; ?>" class="block text-center bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-md transition duration-200">
                    <i class="fas fa-user mr-2"></i>View User Profile
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>

  <!-- JavaScript -->
  <script>
    // Mobile Menu Toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const closeSidebar = document.getElementById('close-sidebar');

    if (mobileMenuButton && mobileSidebar) {
      mobileMenuButton.addEventListener('click', () => {
        mobileSidebar.classList.remove('-translate-x-full');
      });

      if (closeSidebar) {
        closeSidebar.addEventListener('click', () => {
          mobileSidebar.classList.add('-translate-x-full');
        });
      }
    }
  </script>
</body>

</html>