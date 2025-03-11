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
$verify_sql = "SELECT id FROM payment_methods WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($verify_sql);
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // Payment method not found or doesn't belong to the user
  header("Location: payment-methods.php");
  exit();
}
$stmt->close();

// Delete the payment method
$delete_sql = "DELETE FROM payment_methods WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->bind_param("ii", $payment_id, $user_id);

if ($stmt->execute()) {
  // Redirect back to payment methods page with success message
  header("Location: payment-methods.php?deleted=1");
} else {
  // Redirect with error message
  header("Location: payment-methods.php?error=1");
}
$stmt->close();
