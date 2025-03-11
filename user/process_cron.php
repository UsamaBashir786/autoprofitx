<?php
// process_cron.php - Scheduled cron job file for AutoProfitX
// This file should be configured to run via server cron job (e.g. every 15 minutes)
// php /full/path/to/your/site/cron/process_cron.php
// */15 * * * *

// Prevent direct access through browser
if (php_sapi_name() !== 'cli' && !isset($_SERVER['CRON_SECRET']) && $_SERVER['CRON_SECRET'] !== 'your-secret-key-here') {
    die('Access denied. This script can only be run from the command line or with proper authorization.');
}

// Set error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));

// Set timezone
date_default_timezone_set('UTC');

// Log file for cron activities
$log_file = ROOT_PATH . '/logs/cron.log';

// Initialize log
function logMessage($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    $log_message = "[$date] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // If running from CLI, echo the message
    if (php_sapi_name() === 'cli') {
        echo $log_message;
    }
}

logMessage('Cron job started');

// Connect to database
try {
    require_once ROOT_PATH . '/config/db.php';
    logMessage('Database connection established');
} catch (Exception $e) {
    logMessage('Database connection failed: ' . $e->getMessage());
    exit;
}

// Function to process matured investments
function processMaturedInvestments($conn) {
    logMessage('Processing matured investments...');
    
    // Get all active investments that have reached maturity date
    $matured_query = "SELECT * FROM investments 
                       WHERE status = 'active' 
                       AND maturity_date <= NOW()";

    $result = $conn->query($matured_query);
    $processed_count = 0;

    if ($result && $result->num_rows > 0) {
        while ($investment = $result->fetch_assoc()) {
            $conn->begin_transaction();

            try {
                $user_id = $investment['user_id'];
                $total_return = $investment['total_return'];
                $investment_id = $investment['investment_id'];
                $plan_type = $investment['plan_type'];
                $profit = $investment['expected_profit'];

                logMessage("Processing investment ID: {$investment['id']} - User ID: $user_id - Amount: $total_return");

                // Update user's wallet with the total return
                $update_wallet = "UPDATE wallets 
                                   SET balance = balance + ? 
                                   WHERE user_id = ?";

                $stmt = $conn->prepare($update_wallet);
                $stmt->bind_param("di", $total_return, $user_id);
                $stmt->execute();

                // Update investment status
                $update_investment = "UPDATE investments 
                                       SET status = 'completed', 
                                           completion_date = NOW() 
                                       WHERE id = ?";

                $stmt = $conn->prepare($update_investment);
                $stmt->bind_param("i", $investment['id']);
                $stmt->execute();

                // Record profit payout transaction
                $transaction_query = "INSERT INTO transactions (
                        user_id, 
                        transaction_type, 
                        amount, 
                        status, 
                        description, 
                        reference_id,
                        created_at
                    ) VALUES (?, 'profit', ?, 'completed', ?, ?, NOW())";

                $description = "Profit Payout - $plan_type Plan";

                $stmt = $conn->prepare($transaction_query);
                $stmt->bind_param("idss", $user_id, $total_return, $description, $investment_id);
                $stmt->execute();

                $conn->commit();
                $processed_count++;
                logMessage("Successfully processed investment ID: {$investment['id']}");
            } catch (Exception $e) {
                $conn->rollback();
                logMessage("Error processing investment ID {$investment['id']}: " . $e->getMessage());
            }
        }
    }

    logMessage("Processed $processed_count matured investments");
    return $processed_count;
}

// Function to process matured ticket purchases
function processMaturedTicketPurchases($conn) {
    logMessage('Processing matured ticket purchases...');
    
    // Get all active ticket purchases that have reached maturity date
    $matured_query = "SELECT * FROM ticket_purchases 
                       WHERE status = 'active' 
                       AND maturity_date <= NOW()";

    $result = $conn->query($matured_query);
    $processed_count = 0;

    if ($result && $result->num_rows > 0) {
        while ($purchase = $result->fetch_assoc()) {
            $conn->begin_transaction();

            try {
                $user_id = $purchase['user_id'];
                $total_return = $purchase['total_return'];
                $purchase_id = $purchase['purchase_id'];
                $ticket_id = $purchase['ticket_id'];
                $profit = $purchase['expected_profit'];

                logMessage("Processing ticket purchase ID: {$purchase['id']} - User ID: $user_id - Amount: $total_return");

                // Get ticket information for the transaction description
                $ticket_query = "SELECT title FROM movie_tickets WHERE id = ?";
                $stmt = $conn->prepare($ticket_query);
                $stmt->bind_param("i", $ticket_id);
                $stmt->execute();
                $ticket_result = $stmt->get_result();
                $ticket_data = $ticket_result->fetch_assoc();
                $ticket_title = $ticket_data ? $ticket_data['title'] : 'Unknown Ticket';

                // Update user's wallet with the total return
                $update_wallet = "UPDATE wallets 
                                   SET balance = balance + ? 
                                   WHERE user_id = ?";

                $stmt = $conn->prepare($update_wallet);
                $stmt->bind_param("di", $total_return, $user_id);
                $stmt->execute();

                // Update purchase status
                $update_purchase = "UPDATE ticket_purchases 
                                     SET status = 'completed', 
                                         completion_date = NOW() 
                                     WHERE id = ?";

                $stmt = $conn->prepare($update_purchase);
                $stmt->bind_param("i", $purchase['id']);
                $stmt->execute();

                // Record profit payout transaction
                $transaction_query = "INSERT INTO transactions (
                        user_id, 
                        transaction_type, 
                        amount, 
                        status, 
                        description, 
                        reference_id,
                        created_at
                    ) VALUES (?, 'profit', ?, 'completed', ?, ?, NOW())";

                $description = "Profit from $ticket_title ticket";

                $stmt = $conn->prepare($transaction_query);
                $stmt->bind_param("idss", $user_id, $total_return, $description, $purchase_id);
                $stmt->execute();

                $conn->commit();
                $processed_count++;
                logMessage("Successfully processed ticket purchase ID: {$purchase['id']}");
            } catch (Exception $e) {
                $conn->rollback();
                logMessage("Error processing ticket purchase ID {$purchase['id']}: " . $e->getMessage());
            }
        }
    }

    logMessage("Processed $processed_count matured ticket purchases");
    return $processed_count;
}

// Function to process token daily returns
function processTokenReturns($conn) {
    logMessage('Processing token daily returns...');
    
    // This is a bit more complex since it requires tracking the last time interest was paid
    // First, get all active tokens where the last interest date is more than 24 hours ago
    $tokens_query = "SELECT * FROM alpha_tokens 
                    WHERE status = 'active' 
                    AND (last_interest_date IS NULL OR last_interest_date < DATE_SUB(NOW(), INTERVAL 24 HOUR))";
    
    $result = $conn->query($tokens_query);
    $processed_count = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($token = $result->fetch_assoc()) {
            $conn->begin_transaction();
            
            try {
                $user_id = $token['user_id'];
                $token_id = $token['id'];
                
                // Calculate days since last interest (or purchase if never paid interest)
                $reference_date = $token['last_interest_date'] ? $token['last_interest_date'] : $token['purchase_date'];
                $now = new DateTime();
                $last_date = new DateTime($reference_date);
                $days_diff = $now->diff($last_date)->days;
                
                // Only process if at least one day has passed
                if ($days_diff >= 1) {
                    // Calculate interest (6.5% daily)
                    $token_value = $token['token_value'] ?? 3.5; // Default token value if not set
                    $daily_interest_rate = 0.065; // 6.5%
                    
                    // Apply compounding for each day
                    for ($i = 0; $i < $days_diff; $i++) {
                        $interest_amount = $token_value * $daily_interest_rate;
                        $token_value += $interest_amount;
                    }
                    
                    logMessage("Processing token ID: $token_id - User ID: $user_id - New value: $token_value");
                    
                    // Update token value and last interest date
                    $update_token = "UPDATE alpha_tokens 
                                     SET token_value = ?,
                                         last_interest_date = NOW()
                                     WHERE id = ?";
                    
                    $stmt = $conn->prepare($update_token);
                    $stmt->bind_param("di", $token_value, $token_id);
                    $stmt->execute();
                    
                    $processed_count++;
                    logMessage("Successfully processed token ID: $token_id");
                }
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                logMessage("Error processing token ID $token_id: " . $e->getMessage());
            }
        }
    }
    
    logMessage("Processed $processed_count tokens for daily returns");
    return $processed_count;
}

// Process daily check-in rewards expiration
function processDailyCheckInExpiry($conn) {
    logMessage('Processing daily check-in expiry...');
    
    // Reset streaks for users who haven't checked in for more than 24 hours
    $reset_query = "UPDATE user_checkins 
                   SET streak = 0,
                       last_reset = NOW()
                   WHERE last_checkin < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   AND streak > 0";
    
    $result = $conn->query($reset_query);
    $affected_count = $conn->affected_rows;
    
    logMessage("Reset $affected_count user streaks due to expiry");
    return $affected_count;
}

// Process referral commissions
function processReferralCommissions($conn) {
    logMessage('Processing pending referral commissions...');
    
    // Find investments that have been completed but commissions not paid
    $pending_query = "SELECT i.*, u.referred_by 
                     FROM investments i
                     JOIN users u ON i.user_id = u.id
                     WHERE i.status = 'completed' 
                     AND i.referral_commission_paid = 0
                     AND u.referred_by IS NOT NULL";
    
    $result = $conn->query($pending_query);
    $processed_count = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($investment = $result->fetch_assoc()) {
            $conn->begin_transaction();
            
            try {
                $investment_id = $investment['id'];
                $investment_amount = $investment['amount'];
                $referrer_id = $investment['referred_by'];
                $user_id = $investment['user_id'];
                
                // Get commission rate from plan
                $plan_query = "SELECT referral_commission_rate FROM investment_plans WHERE name = ?";
                $stmt = $conn->prepare($plan_query);
                $stmt->bind_param("s", $investment['plan_type']);
                $stmt->execute();
                $plan_result = $stmt->get_result();
                
                if ($plan_result->num_rows > 0) {
                    $plan_data = $plan_result->fetch_assoc();
                    $commission_rate = $plan_data['referral_commission_rate'];
                    
                    // Calculate commission amount
                    $commission_amount = ($investment_amount * $commission_rate) / 100;
                    
                    logMessage("Processing commission for investment ID: $investment_id - Referrer: $referrer_id - Amount: $commission_amount");
                    
                    // Update referrer's wallet
                    $update_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
                    $stmt = $conn->prepare($update_wallet);
                    $stmt->bind_param("di", $commission_amount, $referrer_id);
                    $stmt->execute();
                    
                    // Record commission
                    $commission_query = "INSERT INTO referral_commissions (
                                investment_id,
                                referrer_id,
                                referred_id,
                                investment_amount,
                                commission_rate,
                                commission_amount,
                                status,
                                paid_at
                            ) VALUES (?, ?, ?, ?, ?, ?, 'paid', NOW())";
                    
                    $stmt = $conn->prepare($commission_query);
                    $stmt->bind_param(
                        "iiiddd",
                        $investment_id,
                        $referrer_id,
                        $user_id,
                        $investment_amount,
                        $commission_rate,
                        $commission_amount
                    );
                    $stmt->execute();
                    
                    // Record transaction
                    $reference_id = "INVREF-" . $investment_id;
                    $description = "Referral Commission - Investment";
                    
                    $transaction_query = "INSERT INTO transactions (
                                user_id,
                                transaction_type,
                                amount,
                                status,
                                description,
                                reference_id,
                                created_at
                            ) VALUES (?, 'deposit', ?, 'completed', ?, ?, NOW())";
                    
                    $stmt = $conn->prepare($transaction_query);
                    $stmt->bind_param("idss", $referrer_id, $commission_amount, $description, $reference_id);
                    $stmt->execute();
                    
                    // Mark commission as paid
                    $update_investment = "UPDATE investments SET referral_commission_paid = 1 WHERE id = ?";
                    $stmt = $conn->prepare($update_investment);
                    $stmt->bind_param("i", $investment_id);
                    $stmt->execute();
                    
                    $processed_count++;
                    logMessage("Successfully processed commission for investment ID: $investment_id");
                }
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                logMessage("Error processing commission for investment ID $investment_id: " . $e->getMessage());
            }
        }
    }
    
    logMessage("Processed $processed_count referral commissions");
    return $processed_count;
}

// Function to clean up old data (optional)
function cleanupOldData($conn) {
    logMessage('Performing database cleanup...');
    
    // Example: Delete very old logs (older than 90 days)
    $cleanup_logs = "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $conn->query($cleanup_logs);
    $logs_deleted = $conn->affected_rows;
    
    logMessage("Deleted $logs_deleted old log entries");
    
    // You can add more cleanup operations as needed
    
    return $logs_deleted;
}

// Execute all cron tasks
try {
    // Begin main execution
    logMessage('Beginning cron tasks execution');
    
    // Process matured investments
    $investments_processed = processMaturedInvestments($conn);
    
    // Process matured ticket purchases
    $tickets_processed = processMaturedTicketPurchases($conn);
    
    // Process token returns
    $tokens_processed = processTokenReturns($conn);
    
    // Process daily check-in expiry
    $checkins_reset = processDailyCheckInExpiry($conn);
    
    // Process referral commissions
    $commissions_processed = processReferralCommissions($conn);
    
    // Cleanup old data
    $cleanup_count = cleanupOldData($conn);
    
    // Log summary
    logMessage("Cron job completed successfully");
    logMessage("Summary: $investments_processed investments, $tickets_processed tickets, $tokens_processed tokens, $checkins_reset check-ins reset, $commissions_processed commissions processed");
    
} catch (Exception $e) {
    logMessage('Critical error in cron execution: ' . $e->getMessage());
}

// Close database connection
if (isset($conn)) {
    $conn->close();
    logMessage('Database connection closed');
}

logMessage('Cron job completed');
?>