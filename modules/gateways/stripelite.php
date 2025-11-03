<?php
/**
 * WHMCS Stripe Lite Gateway Module
 * 
 * A lightweight, trust-focused Stripe payment gateway for WHMCS
 * Uses official Stripe PHP SDK for secure, robust payment processing
 * Redirects customers to Stripe Checkout for secure payments
 * 
 * @version 2.0.0
 * @author ProgrammerNomad
 * @link https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Define module metadata
 */
function stripelite_MetaData()
{
    return array(
        'DisplayName' => 'Stripe Lite',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Configuration for Stripe Lite gateway
 */
function stripelite_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Stripe Lite',
        ),
        'mode' => array(
            'FriendlyName' => 'Mode',
            'Type' => 'Dropdown',
            'Options' => array(
                'test' => 'Test Mode (Sandbox)',
                'live' => 'Production Mode (Live)',
            ),
            'Default' => 'test',
            'Description' => 'Select Test mode to use sandbox credentials, or Production mode for live transactions.',
        ),
        'test_publishable_key' => array(
            'FriendlyName' => 'Test Publishable Key',
            'Type' => 'Text',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Enter your Stripe Test Publishable Key (starts with pk_test_)',
        ),
        'test_secret_key' => array(
            'FriendlyName' => 'Test Secret Key',
            'Type' => 'Password',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Enter your Stripe Test Secret Key (starts with sk_test_). Keep this secure!',
        ),
        'live_publishable_key' => array(
            'FriendlyName' => 'Production Publishable Key',
            'Type' => 'Text',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Enter your Stripe Production Publishable Key (starts with pk_live_)',
        ),
        'live_secret_key' => array(
            'FriendlyName' => 'Production Secret Key',
            'Type' => 'Password',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Enter your Stripe Production Secret Key (starts with sk_live_). Keep this secure!',
        ),
        'webhook_secret' => array(
            'FriendlyName' => 'Webhook Signing Secret',
            'Type' => 'Password',
            'Size' => '60',
            'Default' => '',
            'Description' => 'Enter your Webhook Signing Secret from Stripe Dashboard (starts with whsec_). Used for validating webhook events.',
        ),
    );
}

/**
 * Payment form redirect - creates Stripe Checkout Session using SDK
 */
function stripelite_link($params)
{
    try {
        // Load Stripe SDK
        $stripeLibPath = __DIR__ . '/stripelite/vendor/autoload.php';
        if (!file_exists($stripeLibPath)) {
            throw new Exception('Stripe SDK not installed. Download SDK to: modules/gateways/stripelite/');
        }
        require_once $stripeLibPath;

        // Extract gateway parameters
        $mode = $params['mode'];
        $secret_key = ($mode == 'test') 
            ? $params['test_secret_key'] 
            : $params['live_secret_key'];
        
        $invoice_id = $params['invoiceid'];
        $amount = $params['amount'];
        $currency = $params['currency'];
        $client_id = $params['clientdetails']['userid'];
        $client_email = $params['clientdetails']['email'];
        
        // Validate API keys
        if (empty($secret_key)) {
            return renderError('Stripe API keys not configured for ' . $mode . ' mode. Please contact support.');
        }
        
        // Initialize Stripe SDK
        \Stripe\Stripe::setApiKey($secret_key);

        // Build return URLs
        $system_url = rtrim($params['systemurl'], '/');
        $success_url = $system_url . '/modules/gateways/callbacks/stripelite.php?action=return&invoice=' . $invoice_id . '&session_id={CHECKOUT_SESSION_ID}';
        $cancel_url = $system_url . '/cart.php?action=view';

        // Create idempotency key to prevent duplicate sessions
        $idempotency_key = 'whmcs_invoice_' . $invoice_id;

        // Create Checkout Session using Stripe SDK
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => "WHMCS Invoice #{$invoice_id}",
                    ],
                    'unit_amount' => (int)round($amount * 100), // Convert to cents
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $client_email,
            'metadata' => [
                'invoice_id' => (string)$invoice_id,
                'client_id' => (string)$client_id,
                'whmcs_site' => $system_url,
            ],
        ], [
            'idempotency_key' => $idempotency_key,
        ]);

        // Store session mapping in database for reference
        _storeStripeSession($invoice_id, $session->id, $amount, $currency);

        // Log session creation
        logTransaction('stripelite', 'Session ID: ' . $session->id, 'Checkout session created');

        // Redirect to Stripe Checkout (HTTP 303 redirect instead of JavaScript)
        header('Location: ' . $session->url, true, 303);
        exit;

    } catch (\Stripe\Exception\ApiErrorException $e) {
        logTransaction('stripelite', $e->getMessage(), 'Stripe API Error');
        return renderError('Payment error: ' . $e->getMessage());
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error creating checkout session');
        return renderError('Error: ' . $e->getMessage());
    }
}

/**
 * Handle payment return from Stripe (manual validation)
 * This function is called from the callback handler
 */
function stripelite_handleReturn($invoice_id, $session_id, $mode, $secret_key)
{
    // Load Stripe SDK
    $stripeLibPath = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($stripeLibPath)) {
        require_once $stripeLibPath;
        \Stripe\Stripe::setApiKey($secret_key);
        
        try {
            // Retrieve Stripe session details to verify payment
            $session = \Stripe\Checkout\Session::retrieve($session_id);
            
            if ($session->payment_status !== 'paid') {
                logTransaction('stripelite', json_encode($session), 'Session not paid');
                return array(
                    'success' => false,
                    'message' => 'Payment not completed',
                );
            }
            
            // Retrieve payment intent details
            $payment_intent_id = $session->payment_intent;
            if (empty($payment_intent_id)) {
                logTransaction('stripelite', json_encode($session), 'No payment intent found');
                return array(
                    'success' => false,
                    'message' => 'No payment intent found',
                );
            }
            
            // Fetch payment intent to get amount and confirm status
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            if ($intent->status !== 'succeeded') {
                logTransaction('stripelite', json_encode($intent), 'Payment intent not succeeded');
                return array(
                    'success' => false,
                    'message' => 'Payment did not succeed',
                );
            }
            
            // Get amount (Stripe stores in cents)
            $stripe_amount = $intent->amount;
            $stripe_currency = strtoupper($intent->currency);
            
            // Log transaction for tracking
            logTransaction('stripelite', json_encode($intent), 'Payment verified successfully');
            
            return array(
                'success' => true,
                'transaction_id' => $payment_intent_id,
                'amount' => $stripe_amount / 100, // Convert back to dollars
                'currency' => $stripe_currency,
                'invoice_id' => $invoice_id,
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            logTransaction('stripelite', $e->getMessage(), 'Stripe API Error on return');
            return array(
                'success' => false,
                'message' => 'Payment verification error: ' . $e->getMessage(),
            );
        }
    }
    
    return array(
        'success' => false,
        'message' => 'SDK not available',
    );
}

/**
 * Store Stripe session mapping in database (using Capsule)
 */
function _storeStripeSession($invoice_id, $session_id, $amount, $currency)
{
    try {
        // Ensure table exists
        _createSessionTable();

        // Store in database
        Capsule::table('mod_stripelite_sessions')->insert([
            'invoiceid' => $invoice_id,
            'session_id' => $session_id,
            'amount' => $amount,
            'currency' => $currency,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error storing session in DB');
        // Non-critical; session will work via Stripe API lookup
    }
}

/**
 * Create mod_stripelite_sessions table if it doesn't exist (using Capsule)
 */
function _createSessionTable()
{
    if (!Capsule::schema()->hasTable('mod_stripelite_sessions')) {
        Capsule::schema()->create('mod_stripelite_sessions', function($table) {
            $table->increments('id');
            $table->integer('invoiceid')->unsigned();
            $table->string('session_id', 255)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8);
            $table->timestamp('created_at')->useCurrent();
            $table->index('invoiceid');
            $table->index('session_id');
        });
    }
}

/**
 * Check if transaction already exists (prevent duplicates using Capsule)
 */
function _transactionExists($invoice_id, $transaction_id)
{
    try {
        $result = Capsule::table('tblaccounts')
            ->where('invoiceid', $invoice_id)
            ->where('transid', $transaction_id)
            ->where('amountout', 0)
            ->first();
        
        return ($result !== null);
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error checking transaction');
        return false;
    }
}

/**
 * Check if invoice is already paid
 */
function _invoiceAlreadyPaid($invoice_id)
{
    try {
        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoice_id)
            ->first();
        
        return ($invoice && $invoice->status === 'Paid');
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error checking invoice status');
        return false;
    }
}

/**
 * Render error message
 */
function renderError($message)
{
    return '<div style="color:red; padding:15px; background:#ffe0e0; border:1px solid red; border-radius:4px; font-family:Arial, sans-serif;">'
        . '<strong>Payment Error:</strong> ' . htmlspecialchars($message)
        . '</div>';
}

/**
 * Log transaction for debugging
 */
function logTransaction($gateway, $data, $action)
{
    try {
        $log_dir = ROOTDIR . '/logs';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/stripe_lite.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [{$action}] " . substr($data, 0, 500) . "\n";
        
        @file_put_contents($log_file, $log_message, FILE_APPEND);
    } catch (Exception $e) {
        // Silently fail logging to avoid breaking payment flow
    }
}

?>
