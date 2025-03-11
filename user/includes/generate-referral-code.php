<?php
// File: includes/generate-referral-code.php

// Function to generate a unique referral code
function generateUniqueReferralCode($conn, $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $isUnique = false;
    $maxAttempts = 10;
    $attempts = 0;
    
    // Keep trying until we find a unique code or reach max attempts
    while (!$isUnique && $attempts < $maxAttempts) {
        $code = '';
        // Generate a random code
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, $charactersLength - 1)];
        }
        
        // Check if this code already exists in the database
        $check_sql = "SELECT id FROM users WHERE referral_code = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Code is unique
            $isUnique = true;
        }
        
        $stmt->close();
        $attempts++;
    }
    
    // If we couldn't generate a unique code, add user_id to make it unique
    if (!$isUnique) {
        // This is a fallback - we'll add a timestamp to ensure uniqueness
        $code = substr($code, 0, 4) . strtoupper(substr(md5(time()), 0, 4));
    }
    
    return $code;
}

// Function to generate and assign a referral code to an existing user
function assignReferralCodeToUser($conn, $userId) {
    // Generate a unique referral code
    $referralCode = generateUniqueReferralCode($conn);
    
    // Update the user's record with the new referral code
    $update_sql = "UPDATE users SET referral_code = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $referralCode, $userId);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success ? $referralCode : false;
}