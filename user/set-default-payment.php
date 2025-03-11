<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Check if payment method ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: payment-methods.php");
  exit();
}

$payment_id = $_GET['id'];

// Verify the payment method belongs to the user
$verify_sql = "SELECT id, payment_type FROM payment_methods WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($verify_sql);
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // Payment method not found or doesn't belong to the user
  header("Location: payment-methods.php");
  exit();
}

$payment_method = $result->fetch_assoc();
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
  // First, unset all default payment methods for this user
  $unset_sql = "UPDATE payment_methods SET is_default = 0 WHERE user_id = ?";
  $stmt = $conn->prepare($unset_sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->close();

  // Set the selected payment method as default
  $set_sql = "UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?";
  $stmt = $conn->prepare($set_sql);
  $stmt->bind_param("ii", $payment_id, $user_id);
  $stmt->execute();
  $stmt->close();

  // Commit transaction
  $conn->commit();

  // Redirect back to payment methods page with success message
  header("Location: payment-methods.php?default_set=1");
  exit();
} catch (Exception $e) {
  // Rollback transaction on error
  $conn->rollback();

  // Redirect with error message
  header("Location: payment-methods.php?error=1");
  exit();
}
