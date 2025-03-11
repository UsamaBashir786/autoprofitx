<?php
/**
 * Tree Commission System (MySQLi Version)
 * 
 * A complete implementation of a multi-level referral commission system
 * for investment platforms using MySQLi.
 */

/**
 * Get database connection
 * 
 * @return mysqli Database connection
 */
function getConnection() {
    $host = 'localhost';
    $dbname = 'autoproftx';
    $username = 'root';  // Replace with your actual database username
    $password = '';      // Replace with your actual database password
    
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

/**
 * Build the referral tree for a user
 * Maps the hierarchical relationships between users
 * 
 * @param int $userId The ID of the new user
 * @param int $referrerId The ID of the user who referred them
 * @return bool Success status
 */
function buildReferralTree($userId, $referrerId) {
    $conn = getConnection();
    
    // Only proceed if referrer exists
    if (!$referrerId) {
        return false;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Add direct referral relationship (level 1)
        $stmt = $conn->prepare("INSERT INTO referral_tree (user_id, parent_id, level) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $userId, $referrerId);
        $stmt->execute();
        
        // Build up the chain (level 2, 3, etc.)
        $level = 2;
        $currentParent = $referrerId;
        $maxLevels = 10; // Prevent infinite loops
        
        while ($level <= $maxLevels && $currentParent) {
            // Find the referrer's referrer
            $stmt = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->bind_param("i", $currentParent);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if (!$row || !$row['referred_by']) {
                break; // Reached the top of the chain
            }
            
            $currentParent = $row['referred_by'];
            
            // Add this relationship to the tree
            $stmt = $conn->prepare("INSERT INTO referral_tree (user_id, parent_id, level) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $userId, $currentParent, $level);
            $stmt->execute();
            
            $level++;
        }
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error building referral tree: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Calculate and distribute tree commissions when an investment is made
 * 
 * @param int $investmentId The ID of the new investment
 * @return bool Success status
 */
function calculateTreeCommissions($investmentId) {
    $conn = getConnection();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Check if this investment already had commissions processed
        $stmt = $conn->prepare("SELECT referral_commission_paid FROM investments WHERE id = ?");
        $stmt->bind_param("i", $investmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['referral_commission_paid']) {
            return true; // Already processed
        }
        
        // Get investment details
        $stmt = $conn->prepare("SELECT user_id, amount, plan_type, plan_id FROM investments WHERE id = ?");
        $stmt->bind_param("i", $investmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $investment = $result->fetch_assoc();
        
        if (!$investment) {
            return false; // Investment not found
        }
        
        $userId = $investment['user_id'];
        $amount = $investment['amount'];
        
        // Get commission structure
        $stmt = $conn->prepare("SELECT level, commission_rate FROM referral_structure ORDER BY level");
        $stmt->execute();
        $result = $stmt->get_result();
        $commissionRates = [];
        
        while ($row = $result->fetch_assoc()) {
            $commissionRates[$row['level']] = $row['commission_rate'];
        }
        
        // Get all upline users from the referral tree
        $stmt = $conn->prepare("
            SELECT parent_id, level 
            FROM referral_tree 
            WHERE user_id = ? 
            ORDER BY level
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $uplineUsers = [];
        
        while ($row = $result->fetch_assoc()) {
            $uplineUsers[] = $row;
        }
        
        // Process commissions for each upline user
        foreach ($uplineUsers as $upline) {
            $parentId = $upline['parent_id'];
            $level = $upline['level'];
            
            // Skip if no commission rate for this level
            if (!isset($commissionRates[$level])) {
                continue;
            }
            
            $commissionRate = $commissionRates[$level];
            $commissionAmount = $amount * ($commissionRate / 100);
            
            // Record the commission
            $stmt = $conn->prepare("
                INSERT INTO tree_commissions (
                    investment_id, user_id, referred_id, level, 
                    investment_amount, commission_rate, commission_amount, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("iiiddd", $investmentId, $parentId, $userId, $level, $amount, $commissionRate, $commissionAmount);
            $stmt->execute();
            
            // Update wallet balance
            $stmt = $conn->prepare("
                UPDATE wallets 
                SET balance = balance + ?, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->bind_param("di", $commissionAmount, $parentId);
            $stmt->execute();
            
            // Create transaction record
            $description = "Level " . $level . " commission from investment #" . $investmentId;
            $referenceId = "COMM-" . $investmentId . "-" . $level;
            $transactionType = 'profit';
            $status = 'completed';
            
            $stmt = $conn->prepare("
                INSERT INTO transactions (
                    user_id, transaction_type, amount, status, 
                    description, reference_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idsss", $parentId, $transactionType, $commissionAmount, $status, $description, $referenceId);
            $stmt->execute();
            
            // Update commission status to paid
            $paid = 'paid';
            $stmt = $conn->prepare("
                UPDATE tree_commissions
                SET status = ?, paid_at = NOW()
                WHERE investment_id = ? AND user_id = ? AND level = ?
            ");
            $stmt->bind_param("siii", $paid, $investmentId, $parentId, $level);
            $stmt->execute();
        }
        
        // Mark the investment as having had commissions processed
        $stmt = $conn->prepare("
            UPDATE investments
            SET referral_commission_paid = 1
            WHERE id = ?
        ");
        $stmt->bind_param("i", $investmentId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error calculating tree commissions: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Migrate existing users to the referral tree
 * Builds the tree for your existing user base
 * 
 * @return bool Success status
 */
function migrateExistingUsersToTree() {
    $conn = getConnection();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Clear existing referral tree data
        $conn->query("TRUNCATE TABLE referral_tree");
        
        // Get all users with referrers
        $result = $conn->query("SELECT id, referred_by FROM users WHERE referred_by IS NOT NULL");
        
        // Build the tree for each user
        while ($user = $result->fetch_assoc()) {
            buildReferralTree($user['id'], $user['referred_by']);
        }
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error migrating users to tree: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Hook function to be called when a new user is registered
 * 
 * @param int $userId The ID of the new user
 * @param int $referrerId The ID of the user who referred them
 * @return bool Success status
 */
function onUserRegistration($userId, $referrerId) {
    return buildReferralTree($userId, $referrerId);
}

/**
 * Hook function to be called when a new investment is made
 * 
 * @param int $investmentId The ID of the new investment
 * @return bool Success status
 */
function onInvestmentCreated($investmentId) {
    return calculateTreeCommissions($investmentId);
}

/**
 * Get statistics for a user's referral network
 * 
 * @param int $userId The user ID to get stats for
 * @return array Statistics about the user's referral network
 */
function getUserReferralStats($userId) {
    $conn = getConnection();
    $stats = [
        'direct_referrals' => 0,
        'indirect_referrals' => 0,
        'total_commissions' => 0
    ];
    
    try {
        // Direct referrals (level 1)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM referral_tree 
            WHERE parent_id = ? AND level = 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stats['direct_referrals'] = $row ? $row['count'] : 0;
        
        // Indirect referrals (level > 1)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM referral_tree 
            WHERE parent_id = ? AND level > 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stats['indirect_referrals'] = $row ? $row['count'] : 0;
        
        // Total commissions earned
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(commission_amount), 0) as total
            FROM tree_commissions
            WHERE user_id = ? AND status = 'paid'
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stats['total_commissions'] = $row ? $row['total'] : 0;
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting referral stats: " . $e->getMessage());
        return $stats;
    } finally {
        $conn->close();
    }
}