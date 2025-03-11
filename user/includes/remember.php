<?php
// Include this at the top of your pages or in a common include file
session_start();

// Check if user is not logged in but has a remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  // Database connection
  $conn = new mysqli($servername, $username, $password, $dbname);

  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $token = $_COOKIE['remember_token'];

  // Find the token in the database
  $sql = "SELECT r.user_id, u.full_name, u.email FROM remember_tokens r 
            JOIN users u ON r.user_id = u.id
            WHERE r.token = ? AND r.expiry_date > NOW()";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];

    // Update last login
    $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $user['user_id']);
    $updateStmt->execute();
    $updateStmt->close();
  }

  $stmt->close();
  $conn->close();
}
