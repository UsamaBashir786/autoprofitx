<?php
session_start();
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

$query = "SELECT id, purchase_time FROM alpha_tokens WHERE user_id = ? AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$tokens = [];
while ($row = $result->fetch_assoc()) {
  $tokens[] = [
    'id' => $row['id'],
    'purchase_time' => strtotime($row['purchase_time'])
  ];
}

echo json_encode($tokens);
