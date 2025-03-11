<?php
/**
 * Movie Ticket Profit Processor
 * 
 * This script should be run as a cron job every hour to process profits
 * for movie ticket purchases that have reached maturity (24 hours after purchase)
 * 
 * Example cron setting (hourly):
 * 0 * * * * php /path/to/process_ticket_profits.php
 */

// Include database connection
require_once __DIR__ . '/../includes/db_connection.php';

// Log function
function log_message($message) {
    $date = date('Y-m-d H:i:s');
    echo "[$date] $message\n";
    file_put_contents(__DIR__ . '/ticket_profit_log.txt', "[$date] $message\n", FILE_APPEND);
}

log_message("Starting ticket profit processing...");

// Find all eligible purchases
$query = "SELECT p.*, u.email 
          FROM ticket_purchases p
          JOIN users u ON p.user_id = u.id
          WHERE p.status = 'active' 
          AND p.profit_paid = 0
          AND p.maturity_date <= NOW()";

$result = $conn->query($query);

if ($result === false) {
    log_message("Query error: " . $conn->error);
    exit;
}

$processedCount = 0;
$errorCount = 0;

if ($result->num_rows === 0) {
    log_message("No eligible purchases found for profit processing.");
    exit;
}

// Process each eligible purchase
while ($purchase = $result->fetch_assoc()) {
    log_message("Processing purchase ID: {$purchase['purchase_id']} for user: {$purchase['email']}");
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update user's wallet with the profit and initial investment
        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->bind_param("di", $purchase['total_return'], $purchase['user_id']);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update wallet for user ID: {$purchase['user_id']}");
        }
        
        // 2. Record transaction for the profit
        $reference = "TICKET-" . $purchase['id'];
        $description = "Profit from movie ticket purchase ID: {$purchase['purchase_id']}";
        
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, transaction_type, amount, status, description, reference_id) 
                               VALUES (?, 'profit', ?, 'completed', ?, ?)");
        $stmt->bind_param("idss", $purchase['user_id'], $purchase['expected_profit'], $description, $reference);
        $stmt->execute();
        
        // 3. Update purchase status
        $stmt = $conn->prepare("UPDATE ticket_purchases SET status = 'completed', profit_paid = 1, completion_date = NOW() WHERE id = ?");
        $stmt->bind_param("i", $purchase['id']);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        log_message("Successfully processed profit for purchase ID: {$purchase['purchase_id']}. Amount: $" . $purchase['expected_profit']);
        $processedCount++;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        log_message("Error processing purchase ID {$purchase['purchase_id']}: " . $e->getMessage());
        $errorCount++;
    }
}

// Summary
log_message("Processing completed. Processed: $processedCount tickets. Errors: $errorCount");

// Close connection
$conn->close();