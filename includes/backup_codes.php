<?php
// Function to generate a single backup code
function generateBackupCode($length = 10)
{
  // Use characters that are easy to read and type
  $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  $code = '';

  // Generate the code with random characters
  for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
  }

  // Format the code as XXXX-XXXX-XX for better readability
  $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 2);

  return $formatted;
}

// Function to generate multiple backup codes for a user
function generateBackupCodesForUser($userId, $conn, $codeCount = 10)
{
  // First, delete any existing unused backup codes for this user
  $deleteSql = "DELETE FROM backup_codes WHERE user_id = ? AND is_used = 0";
  $deleteStmt = $conn->prepare($deleteSql);
  $deleteStmt->bind_param("i", $userId);
  $deleteStmt->execute();
  $deleteStmt->close();

  // Generate new codes
  $codes = [];
  $insertSql = "INSERT INTO backup_codes (user_id, code) VALUES (?, ?)";
  $insertStmt = $conn->prepare($insertSql);

  for ($i = 0; $i < $codeCount; $i++) {
    $code = generateBackupCode();
    $codes[] = $code;

    $insertStmt->bind_param("is", $userId, $code);
    $insertStmt->execute();
  }

  $insertStmt->close();
  return $codes;
}

// Function to verify a backup code
function verifyBackupCode($code, $userId, $conn)
{
  // Clean up the code (remove dashes if user entered them)
  $cleanCode = str_replace('-', '', $code);

  // Reformat it to match database format
  $formattedCode = substr($cleanCode, 0, 4) . '-' . substr($cleanCode, 4, 4) . '-' . substr($cleanCode, 8, 2);

  // Check if code exists and is unused
  $sql = "SELECT id FROM backup_codes WHERE user_id = ? AND code = ? AND is_used = 0";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("is", $userId, $formattedCode);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $codeId = $row['id'];

    // Mark code as used
    $updateSql = "UPDATE backup_codes SET is_used = 1, used_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $codeId);
    $updateStmt->execute();
    $updateStmt->close();

    $stmt->close();
    return true;
  }

  $stmt->close();
  return false;
}

// Add backup codes to the registration process
// Add this to the user registration process after creating the user
function createBackupCodesAfterRegistration($userId, $conn)
{
  $codes = generateBackupCodesForUser($userId, $conn);
  return $codes;
}
