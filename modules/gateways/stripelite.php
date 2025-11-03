<?php
/**
 * WHMCS Stripe Lite Gateway Module
 * 
 * A lightweight, trust-focused Stripe payment gateway for WHMCS
 * Redirects customers to Stripe Checkout for secure payments
 * 
 * @version 1.0.0
 * @author ProgrammerNomad
 * @link https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

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
 * Payment form redirect
 */
function stripelite_link($params)
{
    // Extract gateway parameters
    $mode = $params['mode'];
    $publishable_key = ($mode == 'test') 
        ? $params['test_publishable_key'] 
        : $params['live_publishable_key'];
    $secret_key = ($mode == 'test') 
        ? $params['test_secret_key'] 
        : $params['live_secret_key'];
    
    $invoice_id = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $client_id = $params['clientdetails']['userid'];
    $client_email = $params['clientdetails']['email'];
    $description = "WHMCS Invoice #" . $invoice_id;
    
    // Validate API keys are set
    if (empty($publishable_key) || empty($secret_key)) {
        return '<div style="color:red; padding:10px; background:#ffe0e0; border:1px solid red; border-radius:4px;">'
            . 'Error: Stripe API keys not configured. Please contact support.'
            . '</div>';
    }
    
    // Prepare return URLs
    $system_url = rtrim($params['systemurl'], '/');
    $return_url = $system_url . '/modules/gateways/callback/stripelite.php?action=return&invoice=' . $invoice_id;
    $cancel_url = $system_url . '/cart.php?action=view';
    
    // Create Stripe API request to create checkout session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    curl_setopt($ch, CURLOPT_POST, true);
    
    $post_data = array(
        'payment_method_types[]' => 'card',
        'mode' => 'payment',
        'success_url' => $return_url . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $cancel_url,
        'line_items[0][price_data][currency]' => strtolower($currency),
        'line_items[0][price_data][product_data][name]' => $description,
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][unit_amount]' => (int)($amount * 100), // Convert to cents
        'customer_email' => $client_email,
        'metadata[invoice_id]' => $invoice_id,
        'metadata[client_id]' => $client_id,
        'metadata[whmcs_site]' => $system_url,
    );
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $session_data = json_decode($response, true);
    
    // Check for errors
    if ($http_code !== 200 || !isset($session_data['url'])) {
        logTransaction('stripelite', $response, 'Error creating checkout session');
        return '<div style="color:red; padding:10px; background:#ffe0e0; border:1px solid red; border-radius:4px;">'
            . 'Error: Unable to create payment session. Please try again or contact support.'
            . '</div>';
    }
    
    // Store session ID in WHMCS for later verification
    $session_id = $session_data['id'];
    _storeStripeSession($invoice_id, $session_id, $amount, $currency);
    
    // Redirect to Stripe Checkout
    return '<script>window.location.href = "' . $session_data['url'] . '";</script>';
}

/**
 * Handle payment return from Stripe (manual validation)
 * This function is called from the callback handler
 */
function stripelite_handleReturn($invoice_id, $session_id, $mode, $secret_key)
{
    // Retrieve Stripe session details to verify payment
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions/' . $session_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $session_data = json_decode($response, true);
    
    if ($http_code !== 200) {
        logTransaction('stripelite', $response, 'Error verifying session: ' . $http_code);
        return array(
            'success' => false,
            'message' => 'Unable to verify payment session',
        );
    }
    
    // Check if payment was successful
    if ($session_data['payment_status'] !== 'paid') {
        logTransaction('stripelite', json_encode($session_data), 'Session not paid');
        return array(
            'success' => false,
            'message' => 'Payment not completed',
        );
    }
    
    // Retrieve payment intent details
    $payment_intent_id = $session_data['payment_intent'];
    if (empty($payment_intent_id)) {
        logTransaction('stripelite', json_encode($session_data), 'No payment intent found');
        return array(
            'success' => false,
            'message' => 'No payment intent found',
        );
    }
    
    // Fetch payment intent to get amount and confirm status
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents/' . $payment_intent_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $intent_data = json_decode($response, true);
    
    if ($http_code !== 200) {
        logTransaction('stripelite', $response, 'Error verifying payment intent');
        return array(
            'success' => false,
            'message' => 'Unable to verify payment',
        );
    }
    
    if ($intent_data['status'] !== 'succeeded') {
        logTransaction('stripelite', json_encode($intent_data), 'Payment intent not succeeded');
        return array(
            'success' => false,
            'message' => 'Payment did not succeed',
        );
    }
    
    // Get amount (Stripe stores in cents)
    $stripe_amount = $intent_data['amount'];
    $stripe_currency = strtoupper($intent_data['currency']);
    
    // Log transaction for tracking
    logTransaction('stripelite', json_encode($intent_data), 'Payment verified successfully');
    
    return array(
        'success' => true,
        'transaction_id' => $payment_intent_id,
        'amount' => $stripe_amount / 100, // Convert back to dollars
        'currency' => $stripe_currency,
        'invoice_id' => $invoice_id,
    );
}

/**
 * Store Stripe session for reference
 */
function _storeStripeSession($invoice_id, $session_id, $amount, $currency)
{
    // This stores session data temporarily for matching on callback
    // In production, store in database if needed
    $_SESSION['stripe_session_' . $invoice_id] = array(
        'session_id' => $session_id,
        'amount' => $amount,
        'currency' => $currency,
        'created' => time(),
    );
}

/**
 * Check if transaction already exists (prevent duplicates)
 */
function _transactionExists($invoice_id, $transaction_id)
{
    $result = select_query(
        'tblaccounts',
        'id',
        array(
            'invoiceid' => $invoice_id,
            'transid' => $transaction_id,
            'amountout' => 0,
        )
    );
    
    return (mysql_num_rows($result) > 0) ? true : false;
}

/**
 * Log transaction for debugging
 */
function logTransaction($gateway, $data, $action)
{
    $file = __DIR__ . '/../../logs/stripe_lite.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$action}] " . substr($data, 0, 500) . "\n";
    
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0755, true);
    }
    
    @file_put_contents($file, $log_entry, FILE_APPEND);
}

?>
