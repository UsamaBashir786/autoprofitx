<?php
// Initialize session and check admin login
session_start();
if (!isset($_SESSION['admin_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
  exit;
}

// Include database connection
include '../config/db.php';

// Check if withdrawal ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Withdrawal ID is required']);
  exit;
}

$withdrawal_id = intval($_GET['id']);

// Get withdrawal details
$query = "SELECT w.*, u.full_name, u.email, a.name as admin_name, a.id as admin_id
          FROM withdrawals w 
          LEFT JOIN users u ON w.user_id = u.id
          LEFT JOIN admin_users a ON w.processed_by = a.id
          WHERE w.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $withdrawal_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
  exit;
}

$withdrawal = $result->fetch_assoc();
$withdrawal_data = [
  'id' => $withdrawal['id'],
  'withdrawal_id' => $withdrawal['withdrawal_id'],
  'user_id' => $withdrawal['user_id'],
  'amount' => $withdrawal['amount'],
  'tax_amount' => $withdrawal['tax_amount'],
  'net_amount' => $withdrawal['net_amount'],
  'payment_type' => $withdrawal['payment_type'],
  'account_name' => $withdrawal['account_name'],
  'account_number' => $withdrawal['account_number'],
  'status' => $withdrawal['status'],
  'notes' => $withdrawal['notes'],
  'created_at' => $withdrawal['created_at'],
  'processed_at' => $withdrawal['processed_at']
];

$user_data = [
  'id' => $withdrawal['user_id'],
  'full_name' => $withdrawal['full_name'],
  'email' => $withdrawal['email']
];

$admin_data = null;
if ($withdrawal['admin_id']) {
  $admin_data = [
    'id' => $withdrawal['admin_id'],
    'name' => $withdrawal['admin_name']
  ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'withdrawal' => $withdrawal_data,
  'user' => $user_data,
  'admin' => $admin_data
]);
exit;
