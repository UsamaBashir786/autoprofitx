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
$admin_query = "SELECT * FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin_data = $admin_result->fetch_assoc();
$admin_name = $admin_data['name'] ?? 'Admin';

// Get pending deposits count for notifications
$pending_query = "SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'";
$pending_result = $conn->query($pending_query);
$pending_deposits = ($pending_result) ? $pending_result->fetch_assoc()['count'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Get form data
  $title = $_POST['title'] ?? '';
  $description = $_POST['description'] ?? '';
  $price = $_POST['price'] ?? 0;

  // Validate
  $errors = [];
  if (empty($title)) $errors[] = "Title is required";
  if (empty($description)) $errors[] = "Description is required";
  if (!is_numeric($price) || $price <= 0) $errors[] = "Price must be a positive number";

  // Handle image upload
  $image_name = '';
  if (isset($_FILES['ticket_image']) && $_FILES['ticket_image']['error'] === 0) {
    $upload_dir = '../uploads/tickets/';

    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
      mkdir($upload_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['ticket_image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_ext, $allowed_extensions)) {
      $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed";
    } else {
      $image_name = 'ticket_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
      $upload_path = $upload_dir . $image_name;

      if (!move_uploaded_file($_FILES['ticket_image']['tmp_name'], $upload_path)) {
        $errors[] = "Failed to upload image";
      }
    }
  } else {
    $errors[] = "Ticket image is required";
  }

  // Insert into database if no errors
  if (empty($errors)) {
    // Check if table exists, create if not
    $table_check = $conn->query("SHOW TABLES LIKE 'movie_tickets'");
    if ($table_check->num_rows == 0) {
      // Create the movie_tickets table
      $create_table = "CREATE TABLE IF NOT EXISTS `movie_tickets` (
        `id` int NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `description` text NOT NULL,
        `price` decimal(15,2) NOT NULL,
        `image` varchar(255) NOT NULL,
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
      )";
      $conn->query($create_table);
    }

    $sql = "INSERT INTO movie_tickets (title, description, price, image, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssds", $title, $description, $price, $image_name);

    if ($stmt->execute()) {
      $success_message = "Movie ticket added successfully!";
    } else {
      $errors[] = "Database error: " . $conn->error;
    }

    $stmt->close();
  }
}

// Get all movie tickets for display
$tickets = [];
$query = "SELECT * FROM movie_tickets ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
  }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Movie Tickets - AutoProftX Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    /* Bootstrap-like styles for Tailwind */
    .alert {
      padding: 0.75rem 1.25rem;
      margin-bottom: 1rem;
      border: 1px solid transparent;
      border-radius: 0.25rem;
    }

    .alert-danger {
      color: #721c24;
      background-color: #f8d7da;
      border-color: #f5c6cb;
    }

    .alert-success {
      color: #155724;
      background-color: #d4edda;
      border-color: #c3e6cb;
    }

    .card {
      position: relative;
      display: flex;
      flex-direction: column;
      min-width: 0;
      word-wrap: break-word;
      background-color: #2d3748;
      background-clip: border-box;
      border: 1px solid rgba(0, 0, 0, 0.125);
      border-radius: 0.25rem;
      margin-bottom: 1.5rem;
    }

    .card-header {
      padding: 0.75rem 1.25rem;
      margin-bottom: 0;
      background-color: #1a202c;
      border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }

    .card-body {
      flex: 1 1 auto;
      min-height: 1px;
      padding: 1.25rem;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-control {
      display: block;
      width: 100%;
      height: calc(1.5em + 0.75rem + 2px);
      padding: 0.375rem 0.75rem;
      font-size: 1rem;
      font-weight: 400;
      line-height: 1.5;
      color: #fff;
      background-color: #4a5568;
      background-clip: padding-box;
      border: 1px solid #4a5568;
      border-radius: 0.25rem;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    textarea.form-control {
      height: auto;
    }

    .form-control-file {
      display: block;
      width: 100%;
    }

    .btn {
      display: inline-block;
      font-weight: 400;
      color: #fff;
      text-align: center;
      vertical-align: middle;
      cursor: pointer;
      user-select: none;
      background-color: transparent;
      border: 1px solid transparent;
      padding: 0.375rem 0.75rem;
      font-size: 1rem;
      line-height: 1.5;
      border-radius: 0.25rem;
      transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .btn-primary {
      color: #fff;
      background-color: #3490dc;
      border-color: #3490dc;
    }

    .btn-sm {
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
      line-height: 1.5;
      border-radius: 0.2rem;
    }

    .btn-danger {
      color: #fff;
      background-color: #e3342f;
      border-color: #e3342f;
    }

    .table {
      width: 100%;
      margin-bottom: 1rem;
      color: #fff;
      border-collapse: collapse;
    }

    .table th,
    .table td {
      padding: 0.75rem;
      vertical-align: top;
      border-top: 1px solid #4a5568;
    }

    .table thead th {
      vertical-align: bottom;
      border-bottom: 2px solid #4a5568;
    }

    .table-bordered {
      border: 1px solid #4a5568;
    }

    .table-bordered th,
    .table-bordered td {
      border: 1px solid #4a5568;
    }

    .badge {
      display: inline-block;
      padding: 0.25em 0.4em;
      font-size: 75%;
      font-weight: 700;
      line-height: 1;
      text-align: center;
      white-space: nowrap;
      vertical-align: baseline;
      border-radius: 0.25rem;
      transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .badge-success {
      color: #fff;
      background-color: #38c172;
    }

    .badge-danger {
      color: #fff;
      background-color: #e3342f;
    }

    .img-thumbnail {
      padding: 0.25rem;
      background-color: #4a5568;
      border: 1px solid #4a5568;
      border-radius: 0.25rem;
      max-width: 100%;
      height: auto;
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
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
              <?php if ($pending_deposits > 0): ?>
                <span class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-red-500 flex items-center justify-center text-xs"><?php echo $pending_deposits; ?></span>
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
          <a href="movie-upload.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-film w-6"></i>
            <span>Movie Tickets</span>
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
          <a href="charts.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-bar w-6"></i>
            <span>Chart</span>
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
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Movie Ticket Management</h2>
        <p class="text-gray-400">Upload and manage movie tickets for users to purchase.</p>
      </div>

      <div class="container">
        <!-- Error messages -->
        <?php if (!empty($errors)): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="mb-0 list-disc pl-4">
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Success message -->
        <?php if (isset($success_message)): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <!-- Upload Form Card -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-6">
          <div class="bg-gray-700 px-4 py-3 border-b border-gray-600">
            <h6 class="text-lg font-bold text-white">Upload New Movie Ticket</h6>
          </div>
          <div class="p-4">
            <form method="POST" enctype="multipart/form-data">
              <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-300 mb-1">Movie Title</label>
                <input type="text" class="bg-gray-700 text-white rounded-md border border-gray-600 w-full px-3 py-2" id="title" name="title" required>
              </div>

              <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                <textarea class="bg-gray-700 text-white rounded-md border border-gray-600 w-full px-3 py-2" id="description" name="description" rows="3" required></textarea>
              </div>

              <div class="mb-4">
                <label for="price" class="block text-sm font-medium text-gray-300 mb-1">Price ($)</label>
                <input type="number" class="bg-gray-700 text-white rounded-md border border-gray-600 w-full px-3 py-2" id="price" name="price" step="0.01" min="0.01" required>
              </div>

              <div class="mb-4">
                <label for="ticket_image" class="block text-sm font-medium text-gray-300 mb-1">Ticket Image</label>
                <input type="file" class="bg-gray-700 text-white rounded-md border border-gray-600 w-full px-3 py-2" id="ticket_image" name="ticket_image" required>
                <small class="text-gray-400">Upload a JPG, JPEG, PNG, or GIF image for the movie ticket</small>
              </div>

              <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded transition duration-200">Upload Ticket</button>
            </form>
          </div>
        </div>

        <!-- Movie Tickets Table -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
          <div class="bg-gray-700 px-4 py-3 border-b border-gray-600">
            <h6 class="text-lg font-bold text-white">All Movie Tickets</h6>
          </div>
          <div class="p-4">
            <div class="overflow-x-auto">
              <table class="w-full border-collapse">
                <thead class="bg-gray-700">
                  <tr>
                    <th class="border border-gray-600 px-4 py-2 text-left">ID</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Image</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Title</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Description</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Price ($)</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Status</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Created At</th>
                    <th class="border border-gray-600 px-4 py-2 text-left">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($tickets)): ?>
                    <tr>
                      <td colspan="8" class="border border-gray-600 px-4 py-2 text-center">No movie tickets found</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                      <tr>
                        <td class="border border-gray-600 px-4 py-2"><?php echo htmlspecialchars($ticket['id']); ?></td>
                        <td class="border border-gray-600 px-4 py-2">
                          <img src="../uploads/tickets/<?php echo htmlspecialchars($ticket['image']); ?>"
                            alt="<?php echo htmlspecialchars($ticket['title']); ?>"
                            class="max-w-[100px] max-h-[100px] object-cover border border-gray-600 rounded">
                        </td>
                        <td class="border border-gray-600 px-4 py-2"><?php echo htmlspecialchars($ticket['title']); ?></td>
                        <td class="border border-gray-600 px-4 py-2"><?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 100))); ?>...</td>
                        <td class="border border-gray-600 px-4 py-2">$<?php echo htmlspecialchars(number_format($ticket['price'], 2)); ?></td>
                        <td class="border border-gray-600 px-4 py-2">
                          <span class="px-2 py-1 rounded-full text-xs <?php echo $ticket['status'] === 'active' ? 'bg-green-500 text-white' : 'bg-red-500text-white'; ?>">
                            <?php echo ucfirst(htmlspecialchars($ticket['status'])); ?>
                          </span>
                        </td>
                        <td class="border border-gray-600 px-4 py-2"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($ticket['created_at']))); ?></td>
                        <td class="border border-gray-600 px-4 py-2">
                          <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm inline-block mb-1">Edit</a>
                          <a href="delete_ticket.php?id=<?php echo $ticket['id']; ?>"
                            class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm inline-block"
                            onclick="return confirm('Are you sure you want to delete this ticket?');">Delete</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- JavaScript for mobile menu -->
  <script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
      document.getElementById('mobile-sidebar').classList.remove('-translate-x-full');
    });

    document.getElementById('close-sidebar').addEventListener('click', function() {
      document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
    });
  </script>

</body>

</html>