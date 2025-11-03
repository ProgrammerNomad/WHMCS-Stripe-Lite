<?php
/**
 * modules/gateways/stripelite.php
 * WHMCS Stripe Lite Gateway Module (v1.0.2)
 *
 * Place composer vendor inside: modules/gateways/stripelite/vendor/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Config\Setting;
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
    $systemUrl = rtrim((string) Setting::getValue('SystemURL'), '/');
    if ($systemUrl === '') {
        $systemUrl = 'https://yourdomain.com';
    }
    $webhookEndpoint = $systemUrl . '/modules/gateways/callback/stripelite.php';

    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Stripe Lite',
        ],
        'mode' => [
            'FriendlyName' => 'Mode',
            'Type'         => 'dropdown',
            'Options'      => [
                'test' => 'Test Mode (Sandbox)',
                'live' => 'Production Mode (Live)',
            ],
            'Default'      => 'test',
            'Description'  => 'Choose Test for sandbox or Live for production.',
        ],
        'test_publishable_key' => [
            'FriendlyName' => 'Test Publishable Key',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Your Stripe test publishable key (pk_test_...)',
        ],
        'test_secret_key' => [
            'FriendlyName' => 'Test Secret Key',
            'Type'         => 'password',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Your Stripe test secret key (sk_test_...). Keep secret.',
        ],
        'live_publishable_key' => [
            'FriendlyName' => 'Production Publishable Key',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Your Stripe live publishable key (pk_live_...)',
        ],
        'live_secret_key' => [
            'FriendlyName' => 'Production Secret Key',
            'Type'         => 'password',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Your Stripe live secret key (sk_live_...). Keep secret.',
        ],
        'webhook_secret' => [
            'FriendlyName' => 'Webhook Signing Secret',
            'Type'         => 'password',
            'Size'         => '80',
            'Default'      => '',
            'Description'  => 'Optional: webhook signing secret from Stripe (whsec_...). <div class="alert alert-info top-margin-5 bottom-margin-5">Webhook Endpoint URL: <code>' . htmlspecialchars($webhookEndpoint, ENT_QUOTES, 'UTF-8') . '</code></div>',
        ]
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
    $successUrl = $systemUrl . '/modules/gateways/callback/stripelite.php?action=return&invoice=' . $invoiceId . '&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;

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
        _sl_log('stripelite', $e->getMessage(), 'Stripe API Error');
        return renderError('Payment gateway error. Please try again later.');
    } catch (Exception $e) {
        _sl_log('stripelite', $e->getMessage(), 'General Error');
        return renderError('Unexpected error. Please contact support.');
    }

    // Store session in DB
    _storeStripeSession($invoiceId, $session->id, $amount, $currency);

    // Return a button so the client has to intentionally begin the Stripe checkout
    return '<div style="text-align:center; margin: 15px 0;">'
        . '<a class="btn btn-primary" href="' . htmlspecialchars($session->url, ENT_QUOTES, 'UTF-8') . '" rel="noopener">'
        . 'Pay with Stripe'
        . '</a>'
        . '</div>';
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
        _sl_log('stripelite', $e->getMessage(), 'Error retrieving session');
        return ['success' => false, 'message' => 'Unable to verify payment session'];
    }

    if (($session->payment_status ?? '') !== 'paid') {
        _sl_log('stripelite', json_encode($session), 'Session not paid');
        return ['success' => false, 'message' => 'Payment not completed'];
    }

    $paymentIntentId = $session->payment_intent ?? null;
    if (!$paymentIntentId) {
        _sl_log('stripelite', json_encode($session), 'No payment intent');
        return ['success' => false, 'message' => 'No payment intent found'];
    }

    try {
        $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        _sl_log('stripelite', $e->getMessage(), 'Error retrieving payment intent');
        return ['success' => false, 'message' => 'Unable to verify payment intent'];
    }

    if (($intent->status ?? '') !== 'succeeded') {
        _sl_log('stripelite', json_encode($intent), 'Payment intent not succeeded');
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
        _sl_log('stripelite', $e->getMessage(), 'Error creating sessions table');
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
        _sl_log('stripelite', $e->getMessage(), 'Error storing session');
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
        _sl_log('stripelite', $e->getMessage(), 'Error checking transaction exists');
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
        _sl_log('stripelite', $e->getMessage(), 'Error checking invoice status');
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
 * Log helper - uses WHMCS built-in logTransaction()
 * Falls back to file logging if WHMCS logger unavailable
 */
function _sl_log($gateway, $data, $action = '')
{
    // Try WHMCS logTransaction first
    if (function_exists('logTransaction')) {
        try {
            logTransaction($gateway, $data, $action);
            return; // Success, stop here
        } catch (Exception $e) {
            // Fall through to file logging
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
        // silent failure
    }
}

?>
