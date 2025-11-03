<?php
/**
 * modules/gateways/callbacks/stripelite.php
 * Return and Webhook handler for WHMCS Stripe Lite
 */

if (!defined('WHMCS')) {
    // Bootstrap WHMCS environment
    require_once __DIR__ . '/../../../init.php';
    require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../../includes/invoicefunctions.php';
}

use WHMCS\Database\Capsule;

// Composer autoload (module vendor)
$vendorAutoload = __DIR__ . '/../stripelite/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

/**
 * Get gateway configuration safely
 */
$gatewayParams = getGatewayVariables('stripelite');
if (!is_array($gatewayParams) || !$gatewayParams['name']) {
    http_response_code(400);
    die('Gateway not active');
}
$mode = $gatewayParams['mode'] ?? 'test';
$secretKey = ($mode === 'test') ? ($gatewayParams['test_secret_key'] ?? '') : ($gatewayParams['live_secret_key'] ?? '');
$webhookSecret = $gatewayParams['webhook_secret'] ?? '';
$systemUrl = rtrim($gatewayParams['systemurl'] ?? '', '/');

// Route: return (GET) or webhook (POST)
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'return') {
    // Customer returned from Stripe
    $invoiceId = isset($_GET['invoice']) ? (int)$_GET['invoice'] : 0;
    $sessionId = isset($_GET['session_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['session_id']) : '';

    if (!$invoiceId || !$sessionId) {
        _sl_log('return_missing', 'Missing invoice or session_id');
        header('Location: ' . $systemUrl . '/cart.php?action=view');
        exit;
    }

    // Prevent duplicates / already paid
    if (function_exists('_invoiceAlreadyPaid') && _invoiceAlreadyPaid($invoiceId)) {
        header('Location: ' . $systemUrl . "/viewinvoice.php?id={$invoiceId}");
        exit;
    }

    // Call main gateway verifier
    require_once __DIR__ . '/../stripelite.php';
    $result = stripelite_handleReturn($invoiceId, $sessionId, $mode, $secretKey);

    if (!$result['success']) {
        _sl_log('return_verify_failed', $result['message'] ?? 'unknown');
        header('Location: ' . $systemUrl . '/cart.php?action=view&paymenterror=1');
        exit;
    }

    $txId = $result['transaction_id'];
    $amount = $result['amount'];
    $fee = $result['fee'] ?? 0;  // Stripe fee extracted from PaymentIntent

    // Duplicate check
    $exists = Capsule::table('tblaccounts')
        ->where('invoiceid', $invoiceId)
        ->where('transid', $txId)
        ->where('amountout', 0)
        ->exists();

    if ($exists) {
        _sl_log('return_duplicate', "Invoice {$invoiceId} tx {$txId}");
        header('Location: ' . $systemUrl . "/viewinvoice.php?id={$invoiceId}");
        exit;
    }

    // Record payment (fee passed as 4th parameter for WHMCS to record)
    try {
        addInvoicePayment($invoiceId, $txId, $amount, $fee, 'stripelite');
        _sl_log('return_success', "Invoice {$invoiceId} paid tx {$txId} amount {$amount} fee {$fee}");
        header('Location: ' . $systemUrl . "/viewinvoice.php?id={$invoiceId}&paymentsuccess=1");
        exit;
    } catch (Exception $e) {
        _sl_log('return_error', $e->getMessage());
        header('Location: ' . $systemUrl . '/cart.php?action=view&paymenterror=1');
        exit;
    }
}

// POST - handle webhook
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($payload)) {
    if (empty($webhookSecret)) {
        _sl_log('webhook_no_secret', 'Webhook secret not set');
        http_response_code(400);
        echo 'Webhook secret not configured';
        exit;
    }

    try {
        \Stripe\Stripe::setApiKey($secretKey);
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    } catch (\UnexpectedValueException $e) {
        _sl_log('webhook_invalid_payload', $e->getMessage());
        http_response_code(400);
        exit;
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        _sl_log('webhook_invalid_signature', $e->getMessage());
        http_response_code(400);
        exit;
    } catch (Exception $e) {
        _sl_log('webhook_error', $e->getMessage());
        http_response_code(400);
        exit;
    }

    $type = $event->type;
    $data = $event->data->object;

    switch ($type) {
        case 'checkout.session.completed':
            $session = $data;
            $invoiceId = isset($session->metadata->invoice_id) ? (int)$session->metadata->invoice_id : 0;
            $paymentIntentId = $session->payment_intent ?? null;
            if (!$invoiceId || !$paymentIntentId) {
                _sl_log('webhook_missing', json_encode($session));
                break;
            }

            // Verify PaymentIntent status
            try {
                $pi = \Stripe\PaymentIntent::retrieve($paymentIntentId);
                if ($pi->status === 'succeeded') {
                    $amount = ($pi->amount_received ?? $pi->amount ?? 0) / 100.0;
                    
                    // Extract fee from Stripe charge (using multiple methods for reliability)
                    $stripeFee = 0;
                    if ($pi->charges && $pi->charges->data && is_array($pi->charges->data) && count($pi->charges->data) > 0) {
                        $charge = $pi->charges->data[0];
                        
                        // Method 1: Get fee from BalanceTransaction (most reliable)
                        if (isset($charge->balance_transaction)) {
                            try {
                                $balanceTxn = \Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
                                $stripeFee = ($balanceTxn->fee ?? 0);
                                _sl_log('webhook_fee_debug', "checkout: Fee from balance_txn: {$stripeFee}");
                            } catch (Exception $e) {
                                _sl_log('webhook_fee_debug', "checkout: balance_txn failed: " . $e->getMessage());
                            }
                        }
                        
                        // Method 2: Try application_fee_amount
                        if ($stripeFee === 0 && isset($charge->application_fee_amount)) {
                            $stripeFee = (int)$charge->application_fee_amount;
                            _sl_log('webhook_fee_debug', "checkout: Fee from app_fee_amount: {$stripeFee}");
                        }
                        
                        // Method 3: Calculate from amount fields
                        if ($stripeFee === 0) {
                            $chargeAmount = (int)($charge->amount ?? 0);
                            $amountCaptured = (int)($charge->amount_captured ?? 0);
                            $stripeFee = $chargeAmount - $amountCaptured;
                            if ($stripeFee > 0) {
                                _sl_log('webhook_fee_debug', "checkout: Fee calculated: {$stripeFee}");
                            }
                        }
                    }
                    
                    $stripeFee = $stripeFee / 100.0;  // Convert to decimal
                    
                    // Dup check
                    $exists = Capsule::table('tblaccounts')->where('invoiceid', $invoiceId)->where('transid', $paymentIntentId)->where('amountout', 0)->exists();
                    if (!$exists) {
                        addInvoicePayment($invoiceId, $paymentIntentId, $amount, $stripeFee, 'stripelite');
                        _sl_log('webhook_recorded', "Invoice {$invoiceId} tx {$paymentIntentId} amount {$amount} fee {$stripeFee}");
                    } else {
                        _sl_log('webhook_duplicate', "Invoice {$invoiceId} tx {$paymentIntentId} already exists");
                    }
                } else {
                    _sl_log('webhook_pi_not_succeeded', json_encode($pi));
                }
            } catch (Exception $e) {
                _sl_log('webhook_exception', $e->getMessage());
            }
            break;

        case 'payment_intent.succeeded':
            // optional fallback
            $intent = $data;
            $paymentIntentId = $intent->id ?? null;
            $invoiceId = isset($intent->metadata->invoice_id) ? (int)$intent->metadata->invoice_id : 0;
            if ($paymentIntentId && $invoiceId) {
                $amount = ($intent->amount_received ?? $intent->amount ?? 0) / 100.0;
                
                // Extract fee from Stripe charge (using multiple methods for reliability)
                $stripeFee = 0;
                if ($intent->charges && $intent->charges->data && is_array($intent->charges->data) && count($intent->charges->data) > 0) {
                    $charge = $intent->charges->data[0];
                    
                    // Method 1: Get fee from BalanceTransaction (most reliable)
                    if (isset($charge->balance_transaction)) {
                        try {
                            $balanceTxn = \Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
                            $stripeFee = ($balanceTxn->fee ?? 0);
                            _sl_log('webhook_fee_debug', "payment_intent: Fee from balance_txn: {$stripeFee}");
                        } catch (Exception $e) {
                            _sl_log('webhook_fee_debug', "payment_intent: balance_txn failed: " . $e->getMessage());
                        }
                    }
                    
                    // Method 2: Try application_fee_amount
                    if ($stripeFee === 0 && isset($charge->application_fee_amount)) {
                        $stripeFee = (int)$charge->application_fee_amount;
                        _sl_log('webhook_fee_debug', "payment_intent: Fee from app_fee_amount: {$stripeFee}");
                    }
                    
                    // Method 3: Calculate from amount fields
                    if ($stripeFee === 0) {
                        $chargeAmount = (int)($charge->amount ?? 0);
                        $amountCaptured = (int)($charge->amount_captured ?? 0);
                        $stripeFee = $chargeAmount - $amountCaptured;
                        if ($stripeFee > 0) {
                            _sl_log('webhook_fee_debug', "payment_intent: Fee calculated: {$stripeFee}");
                        }
                    }
                }
                
                $stripeFee = $stripeFee / 100.0;  // Convert to decimal
                
                $exists = Capsule::table('tblaccounts')->where('invoiceid', $invoiceId)->where('transid', $paymentIntentId)->where('amountout', 0)->exists();
                if (!$exists) {
                    try {
                        addInvoicePayment($invoiceId, $paymentIntentId, $amount, $stripeFee, 'stripelite');
                        _sl_log('webhook_payment_intent', "Recorded invoice {$invoiceId} tx {$paymentIntentId} amount {$amount} fee {$stripeFee}");
                    } catch (Exception $e) {
                        _sl_log('webhook_pi_error', $e->getMessage());
                    }
                } else {
                    _sl_log('webhook_pi_duplicate', "Invoice {$invoiceId} tx {$paymentIntentId} exists");
                }
            }
            break;

        default:
            _sl_log('webhook_unhandled', 'Event: ' . $type);
            break;
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

// If reached here - bad request
http_response_code(400);
echo 'Bad Request';
exit;

?>
