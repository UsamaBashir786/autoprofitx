<?php
include 'config/db.php';
// Check if user is already logged in, redirect to dashboard if true
if (isset($_SESSION['user_id'])) {
  header("Location: user/index.php");
  exit();
}

// Database connection settings
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "autoproftx";   // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$email = $password = "";
$emailErr = $passwordErr = $loginErr = "";
$remember = false;

// Process login form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Get email and password from form
  $email = trim($_POST["email"]);
  $password = $_POST["password"];

  // Validate email
  if (empty($email)) {
    $emailErr = "Email is required";
  } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $emailErr = "Invalid email format";
  }

  // Validate password
  if (empty($password)) {
    $passwordErr = "Password is required";
  }

  // Remember me checkbox
  if (isset($_POST["remember-me"])) {
    $remember = true;
  }

  // If no validation errors, attempt login
  if (empty($emailErr) && empty($passwordErr)) {
    // Prepare SQL query to fetch user data
    $sql = "SELECT id, full_name, email, password FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
      // User found, verify password
      $user = $result->fetch_assoc();

      if (password_verify($password, $user['password'])) {
        // Password is correct, start a new session
        session_start();

        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];

        // Set remember me cookie if selected
        if ($remember) {
          $token = bin2hex(random_bytes(32)); // Generate a secure token

          // Set cookie for 30 days
          setcookie("remember_token", $token, time() + (86400 * 30), "/");

          // Store token in database (you would need a remember_tokens table)
          $tokenSql = "INSERT INTO remember_tokens (user_id, token, expiry_date) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))";
          $tokenStmt = $conn->prepare($tokenSql);
          $tokenStmt->bind_param("is", $user['id'], $token);
          $tokenStmt->execute();
          $tokenStmt->close();
        }

        // Update last login timestamp
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $user['id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Process profits for user after successful login
        require_once 'functions/process_profits.php';
        processUserProfitsIfNeeded($user['id']);

        // Redirect to dashboard
        header("Location: user/index.php");
        exit();
      } else {
        // Password is incorrect
        $loginErr = "Invalid email or password";
      }
    } else {
      // User not found
      $loginErr = "Invalid email or password";
    }

    $stmt->close();
  }
}

$conn->close();
?>
<!doctype html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
</head>

<body class="bg-black text-white font-sans">
  <!-- Navigation -->
  <?php include 'includes/navbar.php'; ?>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="fixed inset-0 bg-black bg-opacity-90 z-50 transform -translate-y-full transition-transform duration-300 ease-in-out">
    <div class="flex flex-col h-full justify-center items-center relative">
      <button id="close-menu-button" class="absolute top-6 right-6 text-gray-300 hover:text-white text-2xl">
        <i class="fas fa-times"></i>
      </button>
      <div class="flex flex-col space-y-6 items-center">
        <a href="index.php" class="text-xl font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link">Home</a>
        <a href="register.php" class="text-xl font-medium text-gray-300 hover:text-yellow-500 transition duration-300 mobile-menu-link">Create Account</a>
      </div>
    </div>
  </div>

  <!-- Login Form -->
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <h2 class="mt-6 text-3xl font-extrabold text-white">
          Sign in to your account
        </h2>
        <p class="mt-2 text-sm text-gray-400">
          Access your AutoProftX investments
        </p>
      </div>

      <div class="mt-8 bg-gradient-to-b from-gray-900 to-black rounded-xl p-8 shadow-2xl border border-gray-800">
        <?php if (!empty($loginErr)): ?>
          <div class="mb-4 bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md flex items-start">
            <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
            <span><?php echo $loginErr; ?></span>
          </div>
        <?php endif; ?>

        <form class="space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
            <div class="mt-1 relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-envelope text-gray-500"></i>
              </div>
              <input type="email" id="email" name="email" value="<?php echo $email; ?>" class="bg-gray-800 <?php echo !empty($emailErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="example@email.com">
            </div>
            <?php if (!empty($emailErr)): ?>
              <p class="mt-1 text-xs text-red-500"><?php echo $emailErr; ?></p>
            <?php endif; ?>
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
            <div class="mt-1 relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-lock text-gray-500"></i>
              </div>
              <input type="password" id="password" name="password" class="bg-gray-800 <?php echo !empty($passwordErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="••••••••">
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <button type="button" id="togglePassword" class="text-gray-500 hover:text-yellow-500 focus:outline-none">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <?php if (!empty($passwordErr)): ?>
              <p class="mt-1 text-xs text-red-500"><?php echo $passwordErr; ?></p>
            <?php endif; ?>
          </div>

          <!-- Remember Me & Forgot Password Section -->
          <div class="flex flex-col sm:flex-row items-center justify-between mt-4 space-y-3 sm:space-y-0">
            <div class="flex items-center">
              <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-yellow-500 focus:ring-yellow-500 border-gray-700 rounded" <?php echo $remember ? 'checked' : ''; ?>>
              <label for="remember-me" class="ml-2 block text-sm text-gray-400">Remember me</label>
            </div>
            <div class="text-sm">
              <a href="forgot-password.php" class="text-yellow-500 hover:text-yellow-400">Forgot your password?</a>
            </div>
          </div>

          <div>
            <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
              Sign in
            </button>
          </div>
        </form>

        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-gray-900 text-gray-400">
                Or continue with
              </span>
            </div>
          </div>

          <div class="mt-6 text-center">
            <p class="text-sm text-gray-400">
              Don't have an account?
              <a href="register.php" class="text-yellow-500 hover:text-yellow-400">
                Create one now
              </a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>
</body>

</html>