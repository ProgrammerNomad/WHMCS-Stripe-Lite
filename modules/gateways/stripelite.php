<?php
/**
 * modules/gateways/stripelite.php
 * WHMCS Stripe Lite Gateway Module (v2.0.1)
 *
 * Place composer vendor inside: modules/gateways/stripelite/vendor/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Metadata
 */
function stripelite_MetaData()
{
    return [
        'DisplayName' => 'Stripe Lite',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * Configuration
 */
function stripelite_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Stripe Lite',
        ],
        'mode' => [
            'FriendlyName' => 'Mode',
            'Type' => 'Dropdown',
            'Options' => ['test' => 'Test Mode (Sandbox)', 'live' => 'Production Mode (Live)'],
            'Default' => 'test',
            'Description' => 'Select Test or Live mode',
        ],
        'test_publishable_key' => [
            'FriendlyName' => 'Test Publishable Key',
            'Type' => 'Text',
            'Size' => '60',
        ],
        'test_secret_key' => [
            'FriendlyName' => 'Test Secret Key',
            'Type' => 'Password',
            'Size' => '60',
        ],
        'live_publishable_key' => [
            'FriendlyName' => 'Production Publishable Key',
            'Type' => 'Text',
            'Size' => '60',
        ],
        'live_secret_key' => [
            'FriendlyName' => 'Production Secret Key',
            'Type' => 'Password',
            'Size' => '60',
        ],
        'webhook_secret' => [
            'FriendlyName' => 'Webhook Signing Secret',
            'Type' => 'Password',
            'Size' => '60',
            'Description' => 'Webhook signing secret (whsec_...) (recommended)',
        ],
    ];
}

/**
 * Gateway link - create Stripe Checkout Session and redirect
 * Returns HTML if not redirecting (error)
 */
function stripelite_link($params)
{
    // Load composer autoload inside module
    $vendorAutoload = __DIR__ . '/stripelite/vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        return renderError('Stripe SDK not installed. Run "composer require stripe/stripe-php" inside modules/gateways/stripelite.');
    }
    require_once $vendorAutoload;

    // Fetch gateway settings
    $mode = $params['mode'] ?? 'test';
    $secretKey = ($mode === 'test') ? ($params['test_secret_key'] ?? '') : ($params['live_secret_key'] ?? '');
    $systemUrl = rtrim($params['systemurl'], '/');

    // Basic validation
    if (empty($secretKey)) {
        return renderError('Stripe secret key not configured for ' . htmlspecialchars($mode));
    }

    // Invoice / client details
    $invoiceId = (int)($params['invoiceid'] ?? 0);
    $amount = (float)($params['amount'] ?? 0.0);
    $currency = strtoupper($params['currency'] ?? 'USD');
    $clientEmail = $params['clientdetails']['email'] ?? null;
    $clientId = $params['clientdetails']['userid'] ?? null;

    if (!$invoiceId || !$amount || !$clientEmail) {
        return renderError('Missing invoice, amount, or client email.');
    }

    // Initialize Stripe
    \Stripe\Stripe::setApiKey($secretKey);

    // Build URLs
    $successUrl = $systemUrl . '/modules/gateways/callbacks/stripelite.php?action=return&invoice=' . $invoiceId . '&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = $systemUrl . '/cart.php?action=view';

    // Create idempotency key
    $idempotencyKey = 'whmcs_invoice_' . $invoiceId . '_' . time();

    // Create session
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => ['name' => "WHMCS Invoice #{$invoiceId}"],
                    'unit_amount' => intval(round($amount * 100)),
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $clientEmail,
            'metadata' => [
                'invoice_id' => (string)$invoiceId,
                'client_id' => (string)$clientId,
                'whmcs_site' => $systemUrl,
            ],
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        logTransaction('stripelite', $e->getMessage(), 'Stripe API Error');
        return renderError('Payment gateway error. Please try again later.');
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'General Error');
        return renderError('Unexpected error. Please contact support.');
    }

    // Store session in DB
    _storeStripeSession($invoiceId, $session->id, $amount, $currency);

    // Redirect to Stripe Checkout
    header('Location: ' . $session->url, true, 303);
    exit;
}

/**
 * Manual payment verification used by callbacks file
 * Returns array with success, transaction_id, amount, currency, invoice_id
 */
function stripelite_handleReturn($invoice_id, $session_id, $mode, $secret_key)
{
    // Load SDK
    $vendorAutoload = __DIR__ . '/stripelite/vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        return ['success' => false, 'message' => 'Stripe SDK not installed'];
    }
    require_once $vendorAutoload;

    \Stripe\Stripe::setApiKey($secret_key);

    try {
        $session = \Stripe\Checkout\Session::retrieve($session_id);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error retrieving session');
        return ['success' => false, 'message' => 'Unable to verify payment session'];
    }

    if (($session->payment_status ?? '') !== 'paid') {
        logTransaction('stripelite', json_encode($session), 'Session not paid');
        return ['success' => false, 'message' => 'Payment not completed'];
    }

    $paymentIntentId = $session->payment_intent ?? null;
    if (!$paymentIntentId) {
        logTransaction('stripelite', json_encode($session), 'No payment intent');
        return ['success' => false, 'message' => 'No payment intent found'];
    }

    try {
        $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error retrieving payment intent');
        return ['success' => false, 'message' => 'Unable to verify payment intent'];
    }

    if (($intent->status ?? '') !== 'succeeded') {
        logTransaction('stripelite', json_encode($intent), 'Payment intent not succeeded');
        return ['success' => false, 'message' => 'Payment did not succeed'];
    }

    $stripeAmount = $intent->amount_received ?? $intent->amount ?? 0;
    $stripeCurrency = strtoupper($intent->currency ?? '');

    return [
        'success' => true,
        'transaction_id' => $paymentIntentId,
        'amount' => ($stripeAmount / 100.0),
        'currency' => $stripeCurrency,
        'invoice_id' => $invoice_id,
    ];
}

/**
 * DB helpers
 */
function _createSessionTable()
{
    try {
        if (!Capsule::schema()->hasTable('mod_stripelite_sessions')) {
            Capsule::schema()->create('mod_stripelite_sessions', function ($table) {
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
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error creating sessions table');
    }
}

function _storeStripeSession($invoice_id, $session_id, $amount, $currency)
{
    try {
        _createSessionTable();
        Capsule::table('mod_stripelite_sessions')->insert([
            'invoiceid' => $invoice_id,
            'session_id' => $session_id,
            'amount' => $amount,
            'currency' => $currency,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error storing session');
    }
}

/**
 * Duplicate check using tblaccounts
 */
function _transactionExists($invoice_id, $transaction_id)
{
    try {
        $exists = Capsule::table('tblaccounts')
            ->where('invoiceid', $invoice_id)
            ->where('transid', $transaction_id)
            ->where('amountout', 0)
            ->exists();
        return $exists;
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error checking transaction exists');
        return false;
    }
}

/**
 * Invoice paid check
 */
function _invoiceAlreadyPaid($invoice_id)
{
    try {
        $invoice = Capsule::table('tblinvoices')->where('id', $invoice_id)->first();
        return ($invoice && ($invoice->status === 'Paid'));
    } catch (Exception $e) {
        logTransaction('stripelite', $e->getMessage(), 'Error checking invoice status');
        return false;
    }
}

/**
 * Render user-friendly error
 */
function renderError($message)
{
    return '<div style="color:red;padding:12px;background:#fff0f0;border:1px solid #ffcccc;border-radius:4px">'
        . '<strong>Payment Error:</strong> ' . htmlspecialchars($message)
        . '</div>';
}

/**
 * Log wrapper (uses WHMCS logTransaction as well)
 */
function logTransaction($gateway, $data, $action = '')
{
    // Use WHMCS logTransaction if available
    if (function_exists('logTransaction')) {
        try {
            \logTransaction($gateway, $data, $action);
        } catch (Exception $e) {
            // fallback to file
        }
    }

    // File log fallback
    try {
        $log_dir = defined('ROOTDIR') ? ROOTDIR . '/logs' : __DIR__ . '/logs';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        $file = $log_dir . '/stripe_lite.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] [{$action}] " . substr($data, 0, 1000) . PHP_EOL;
        @file_put_contents($file, $entry, FILE_APPEND);
    } catch (Exception $e) {
        // silent
    }
}

?>
