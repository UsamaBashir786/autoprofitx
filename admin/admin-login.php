<?php
// Start session
session_start();
include '../config/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
  header("Location: index.php");
  exit();
}

// Predefined admin credentials - CHANGE THESE FOR SECURITY
$admin_username = "admin";
$admin_password = "admin123"; // This will be hashed before storing
$admin_name = "Administrator";
$admin_email = "admin@autoproftx.com";

$error_message = "";

// Create admin_users table if it doesn't exist
$check_admin_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'admin_users'";
$check_admin_result = $conn->query($check_admin_table_sql);
$admin_table_exists = ($check_admin_result && $check_admin_result->fetch_assoc()['count'] > 0);

if (!$admin_table_exists) {
  $create_admin_table_sql = "CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )";

  $conn->query($create_admin_table_sql);

  // Insert the predefined admin
  $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
  $insert_admin_sql = "INSERT INTO admin_users (username, name, email, password, role) 
                      VALUES (?, ?, ?, ?, 'super_admin')";
  $admin_stmt = $conn->prepare($insert_admin_sql);
  $admin_stmt->bind_param("ssss", $admin_username, $admin_name, $admin_email, $hashed_password);
  $admin_stmt->execute();
  $admin_stmt->close();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  // Validate input
  if (empty($username) || empty($password)) {
    $error_message = "Please enter both username and password";
  } else {
    // Check admin credentials
    $sql = "SELECT id, username, name, password, role, status FROM admin_users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $admin = $result->fetch_assoc();

      // Verify password
      if (password_verify($password, $admin['password'])) {
        // Check if account is active
        if ($admin['status'] === 'active') {
          // Set session variables
          $_SESSION['admin_id'] = $admin['id'];
          $_SESSION['admin_username'] = $admin['username'];
          $_SESSION['admin_name'] = $admin['name'];
          $_SESSION['admin_role'] = $admin['role'];

          // Update last login time
          $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
          $update_stmt = $conn->prepare($update_sql);
          $update_stmt->bind_param("i", $admin['id']);
          $update_stmt->execute();
          $update_stmt->close();

          // Redirect to dashboard
          header("Location: index.php");
          exit();
        } else {
          $error_message = "Your account is inactive. Please contact the super admin.";
        }
      } else {
        $error_message = "Invalid username or password";
      }
    } else {
      $error_message = "Invalid username or password";
    }

    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - AutoProftX</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #111827;
      background-image:
        radial-gradient(circle at 25% 25%, rgba(234, 179, 8, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(234, 179, 8, 0.05) 0%, transparent 50%);
    }

    .btn-shine {
      position: relative;
      overflow: hidden;
    }

    .btn-shine:after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(to right,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 255, 255, 0.1) 50%,
          rgba(255, 255, 255, 0) 100%);
      transform: rotate(30deg);
      animation: shine 6s infinite linear;
    }

    @keyframes shine {
      0% {
        left: -50%;
      }

      100% {
        left: 150%;
      }
    }

    .input-focus-effect:focus {
      box-shadow: 0 0 0 2px #111827, 0 0 0 4px #d97706;
    }

    .login-card {
      background: rgba(31, 41, 55, 0.8);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(75, 85, 99, 0.5);
    }

    .logo-glow {
      filter: drop-shadow(0 0 8px rgba(234, 179, 8, 0.5));
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-10">
      <div class="flex items-center justify-center logo-glow">
        <div class="relative">
          <div class="absolute -inset-1 bg-yellow-500 rounded-full opacity-20 blur-md"></div>
          <div class="h-14 w-14 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center relative">
            <i class="fas fa-chart-line text-black text-xl"></i>
          </div>
        </div>
        <h1 class="ml-3 text-4xl font-bold text-white">
          Auto<span class="text-yellow-500">ProftX</span>
        </h1>
      </div>
    </div>

    <!-- Login Card -->
    <div class="login-card rounded-xl shadow-2xl overflow-hidden">
      <!-- Card Header -->
      <div class="bg-gradient-to-r from-yellow-600 to-yellow-500 px-6 py-4">
        <div class="flex items-center space-x-2">
          <i class="fas fa-shield-alt text-black"></i>
          <h3 class="font-semibold text-black">Admin Panel</h3>
        </div>
      </div>

      <!-- Card Body -->
      <div class="p-8">
        <?php if (!empty($error_message)): ?>
          <div class="bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md mb-6 flex items-start">
            <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
            <span><?php echo $error_message; ?></span>
          </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
          <!-- Username Field -->
          <div>
            <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
              <i class="fas fa-user-shield mr-2 text-yellow-500"></i>Admin Username or Email
            </label>
            <div class="relative">
              <input
                id="username"
                name="username"
                type="text"
                required
                class="bg-gray-800 w-full px-4 py-3 rounded-lg border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:ring-0 input-focus-effect transition duration-200"
                placeholder="Enter your credentials">
            </div>
          </div>

          <!-- Password Field -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
              <i class="fas fa-lock mr-2 text-yellow-500"></i>Password
            </label>
            <div class="relative">
              <input
                id="password"
                name="password"
                type="password"
                required
                class="bg-gray-800 w-full px-4 py-3 rounded-lg border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:ring-0 input-focus-effect transition duration-200"
                placeholder="Enter your password">
              <button
                type="button"
                id="togglePassword"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-yellow-500 focus:outline-none transition duration-200">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input
                id="remember-me"
                name="remember-me"
                type="checkbox"
                class="h-4 w-4 bg-gray-800 border-gray-700 rounded text-yellow-500 focus:ring-yellow-500 focus:ring-offset-gray-800">
              <label for="remember-me" class="ml-2 block text-sm text-gray-400">
                Remember this device
              </label>
            </div>
          </div>

          <!-- Sign In Button -->
          <div>
            <button
              type="submit"
              class="btn-shine w-full flex justify-center items-center px-4 py-3 bg-gradient-to-r from-yellow-600 to-yellow-500 text-black font-semibold rounded-lg hover:from-yellow-500 hover:to-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transform hover:scale-[1.02] transition-all duration-200">
              <i class="fas fa-unlock-alt mr-2"></i>
              Sign In to Dashboard
            </button>
          </div>

          <!-- Demo Credentials -->
          <div class="mt-4 bg-gray-800 bg-opacity-50 rounded-lg p-3 border border-gray-700">
            <p class="text-sm text-gray-400 flex items-center">
              <i class="fas fa-info-circle text-yellow-500 mr-2"></i>
              <span>Demo Credentials: <span class="text-yellow-400 font-medium"><?php echo htmlspecialchars($admin_username); ?> / <?php echo htmlspecialchars($admin_password); ?></span></span>
            </p>
          </div>
        </form>
      </div>
    </div>

    <!-- Back to site -->
    <div class="text-center mt-8">
      <a href="../index.php" class="inline-flex items-center text-gray-400 hover:text-yellow-500 transition duration-200">
        <i class="fas fa-chevron-left mr-2"></i>
        Return to Main Platform
      </a>
    </div>

    <!-- Footer -->
    <div class="text-center mt-16">
      <p class="text-gray-500 text-sm">
        &copy; 2025 AutoProftX. All rights reserved.
      </p>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle password visibility
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');

      togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
      });
    });
  </script>
</body>

</html>