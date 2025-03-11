<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'User not authenticated']);
  exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Invalid deposit ID']);
  exit();
}

$deposit_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Query deposit details
$query = "SELECT d.*, pm.payment_type as user_payment_type, apm.payment_type as admin_payment_type, 
           apm.account_name as admin_account_name, apm.account_number as admin_account_number 
           FROM deposits d
           LEFT JOIN payment_methods pm ON d.payment_method_id = pm.id
           LEFT JOIN admin_payment_methods apm ON d.admin_payment_id = apm.id
           WHERE d.id = ? AND d.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $deposit_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Deposit not found or access denied']);
  exit();
}

$deposit = $result->fetch_assoc();
$stmt->close();

// Prepare response
header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'deposit' => $deposit
]);
exit();
