<?php
/**
 * WHMCS Stripe Lite - Callback & Webhook Handler
 * 
 * Handles payment return from Stripe Checkout
 * Processes webhook events as fallback payment confirmation
 * Uses official Stripe PHP SDK for webhook verification
 * 
 * @version 2.0.0
 * @author ProgrammerNomad
 */

// Require WHMCS init
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

// Check action
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action == 'return') {
    // Handle return from Stripe Checkout (immediate payment validation)
    handleStripeReturn();
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    // Handle webhook event
    handleStripeWebhook();
} else {
    http_response_code(400);
    die('Invalid request');
}

/**
 * Handle return from Stripe Checkout
 * Called when customer is redirected back from Stripe after completing payment
 */
function handleStripeReturn()
{
    $invoice_id = isset($_GET['invoice']) ? (int)$_GET['invoice'] : 0;
    $session_id = isset($_GET['session_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['session_id']) : '';
    
    if (!$invoice_id || !$session_id) {
        logStripeTransaction('', 'Invalid return parameters');
        die('Invalid request');
    }
    
    // Get gateway configuration
    $gatewayParams = getGatewayVariables('stripelite');
    if (!$gatewayParams['type']) {
        logStripeTransaction('', 'Gateway not found or disabled');
        die('Gateway configuration error');
    }
    
    $mode = $gatewayParams['mode'];
    $secret_key = ($mode == 'test')
        ? $gatewayParams['test_secret_key']
        : $gatewayParams['live_secret_key'];
    
    if (empty($secret_key)) {
        logStripeTransaction('', 'Secret key not configured for mode: ' . $mode);
        die('Gateway configuration incomplete');
    }
    
    // Call the validation function from main gateway
    require_once __DIR__ . '/stripelite.php';
    $result = stripelite_handleReturn($invoice_id, $session_id, $mode, $secret_key);
    
    if (!$result['success']) {
        logStripeTransaction('', 'Payment validation failed: ' . $result['message']);
        header('Location: ' . $gatewayParams['systemurl'] . 'cart.php?action=view');
        die();
    }
    
    // Payment verified - now mark invoice as paid
    $transaction_id = $result['transaction_id'];
    $amount = $result['amount'];
    
    logStripeTransaction($transaction_id, 'Payment verified. Amount: ' . $amount . ', Invoice: ' . $invoice_id);
    
    // Check if transaction already exists (prevent duplicates)
    $existingTransaction = Capsule::table('tblaccounts')
        ->where('invoiceid', $invoice_id)
        ->where('transid', $transaction_id)
        ->where('amountout', 0)
        ->first();
    
    if ($existingTransaction) {
        logStripeTransaction($transaction_id, 'Transaction already recorded. Skipping duplicate entry.');
        header('Location: ' . $gatewayParams['systemurl'] . 'cart.php?action=view&paid=1');
        die();
    }
    
    // Add transaction to WHMCS (mark invoice as paid)
    try {
        addInvoicePayment(
            $invoice_id,
            $transaction_id,
            $amount,
            0, // Fee (no fee for Stripe Lite)
            'stripelite'
        );
        
        logStripeTransaction($transaction_id, 'Invoice marked as paid successfully');
        
        // Redirect to payment success page
        header('Location: ' . $gatewayParams['systemurl'] . 'cart.php?action=view&paid=1');
        die();
        
    } catch (Exception $e) {
        logStripeTransaction($transaction_id, 'Error marking invoice as paid: ' . $e->getMessage());
        header('Location: ' . $gatewayParams['systemurl'] . 'cart.php?action=view');
        die();
    }
}

/**
 * Handle Stripe Webhook Events
 * Acts as fallback in case customer doesn't return to WHMCS after payment
 * Uses Stripe SDK for webhook signature verification
 */
function handleStripeWebhook()
{
    // Load Stripe SDK
    $stripeLibPath = __DIR__ . '/../stripelite/vendor/autoload.php';
    if (!file_exists($stripeLibPath)) {
        logStripeTransaction('', 'Webhook: Stripe SDK not installed');
        http_response_code(500);
        die('SDK not available');
    }
    require_once $stripeLibPath;
    
    // Get gateway configuration
    $gatewayParams = getGatewayVariables('stripelite');
    if (!$gatewayParams['type']) {
        logStripeTransaction('', 'Webhook: Gateway not found');
        http_response_code(404);
        die('Gateway not found');
    }
    
    $webhook_secret = $gatewayParams['webhook_secret'];
    
    if (empty($webhook_secret)) {
        logStripeTransaction('', 'Webhook: Webhook secret not configured');
        http_response_code(400);
        die('Webhook secret not configured');
    }
    
    // Get raw request body and signature header
    $input = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    if (empty($input) || empty($sig_header)) {
        logStripeTransaction('', 'Webhook: Missing body or signature header');
        http_response_code(400);
        die('Invalid request');
    }
    
    try {
        // Verify webhook signature using Stripe SDK
        $event = \Stripe\Webhook::constructEvent($input, $sig_header, $webhook_secret);
        
        // Handle specific event types
        switch ($event->type) {
            case 'checkout.session.completed':
                handleCheckoutSessionCompleted($event->data->object, $gatewayParams);
                break;
            case 'payment_intent.succeeded':
                handlePaymentIntentSucceeded($event->data->object, $gatewayParams);
                break;
            default:
                // Acknowledge other event types
                logStripeTransaction('', 'Webhook: Unhandled event type ' . $event->type);
                break;
        }
        
        http_response_code(200);
        echo json_encode(['success' => true]);
        
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        logStripeTransaction('', 'Webhook: Invalid signature - ' . $e->getMessage());
        http_response_code(403);
        die('Invalid signature');
    } catch (Exception $e) {
        logStripeTransaction('', 'Webhook: Error processing event - ' . $e->getMessage());
        http_response_code(400);
        die('Error processing webhook');
    }
    
    die();
}

/**
 * Handle checkout.session.completed event
 */
function handleCheckoutSessionCompleted($session, $gatewayParams)
{
    $invoice_id = isset($session->metadata->invoice_id) ? (int)$session->metadata->invoice_id : 0;
    $payment_intent_id = $session->payment_intent;
    $amount = $session->amount_total / 100; // Convert from cents
    
    if (!$invoice_id || !$payment_intent_id) {
        logStripeTransaction('', 'Webhook: Missing invoice_id or payment_intent_id');
        return;
    }
    
    logStripeTransaction($payment_intent_id, 'Webhook: checkout.session.completed for invoice ' . $invoice_id);
    
    // Check if transaction already exists (prevent duplicates)
    $existingTransaction = Capsule::table('tblaccounts')
        ->where('invoiceid', $invoice_id)
        ->where('transid', $payment_intent_id)
        ->where('amountout', 0)
        ->first();
    
    if ($existingTransaction) {
        logStripeTransaction($payment_intent_id, 'Webhook: Transaction already recorded. Skipping.');
        return;
    }
    
    // Mark invoice as paid
    try {
        addInvoicePayment(
            $invoice_id,
            $payment_intent_id,
            $amount,
            0,
            'stripelite'
        );
        
        logStripeTransaction($payment_intent_id, 'Webhook: Invoice marked as paid via webhook');
        
    } catch (Exception $e) {
        logStripeTransaction($payment_intent_id, 'Webhook: Error marking invoice: ' . $e->getMessage());
    }
}

/**
 * Handle payment_intent.succeeded event (optional backup)
 */
function handlePaymentIntentSucceeded($intent, $gatewayParams)
{
    // This is an optional fallback - mostly handled by checkout.session.completed
    $payment_intent_id = $intent->id;
    
    // Check metadata for invoice ID
    if (!isset($intent->metadata->invoice_id)) {
        logStripeTransaction($payment_intent_id, 'Webhook: No invoice metadata in payment_intent');
        return;
    }
    
    $invoice_id = (int)$intent->metadata->invoice_id;
    $amount = $intent->amount_received / 100; // Convert from cents
    
    logStripeTransaction($payment_intent_id, 'Webhook: payment_intent.succeeded for invoice ' . $invoice_id);
    
    // Check if already recorded
    $existingTransaction = Capsule::table('tblaccounts')
        ->where('invoiceid', $invoice_id)
        ->where('transid', $payment_intent_id)
        ->where('amountout', 0)
        ->first();
    
    if ($existingTransaction) {
        logStripeTransaction($payment_intent_id, 'Webhook: Transaction already recorded via payment_intent.');
        return;
    }
    
    try {
        addInvoicePayment(
            $invoice_id,
            $payment_intent_id,
            $amount,
            0,
            'stripelite'
        );
        
        logStripeTransaction($payment_intent_id, 'Webhook: Invoice marked via payment_intent');
        
    } catch (Exception $e) {
        logStripeTransaction($payment_intent_id, 'Webhook: Error marking invoice: ' . $e->getMessage());
    }
}

/**
 * Log Stripe transactions for debugging
 */
function logStripeTransaction($transaction_id, $message)
{
    $log_dir = __DIR__ . '/../../logs';
    $log_file = $log_dir . '/stripe_lite.log';
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [TxnID: {$transaction_id}] {$message}\n";
    
    @file_put_contents($log_file, $log_message, FILE_APPEND);
}

?>
