# 🟦 WHMCS Stripe Lite

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![WHMCS](https://img.shields.io/badge/WHMCS-v8.13-success)](https://www.whmcs.com/)
[![Stripe](https://img.shields.io/badge/Stripe-Checkout-blueviolet.svg)](https://stripe.com/)
[![GitHub Repo](https://img.shields.io/badge/GitHub-ProgrammerNomad%2FWHMCS--Stripe--Lite-black)](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite)

> A lightweight, trust-focused Stripe payment gateway for WHMCS that redirects customers to Stripe's official Checkout page for secure payments.

---

## Overview

**WHMCS Stripe Lite** is a simplified and trust-oriented alternative to the official WHMCS Stripe module. Rather than collecting card details on your site, Stripe Lite redirects customers to Stripe Checkout — a Stripe-hosted, PCI-compliant payment page — increasing security and customer trust while preserving WHMCS automation.

This reduces friction for customers who are hesitant to enter card details on new or unfamiliar sites and helps lower cart abandonment.

---

## Features

- Seamless integration with WHMCS v8.0+
- Redirects customers to Stripe Checkout (no card input on your server)
- Matches the core functionality of the official Stripe gateway
- Improves customer trust and conversions
- Easy setup and configuration
- Supports all currencies and payment methods provided by Stripe
- **Dual Mode Support**: Test (Sandbox) and Production (Live) modes with separate API keys
- **Manual Payment Verification**: Verifies payment status directly from Stripe API upon redirect
- **Idempotent Transaction Handling**: Prevents duplicate invoice payments
- **Webhook Fallback**: Automatic payment confirmation if customer doesn't redirect back to WHMCS

---

## Installation

### Step 1: Download the Module

Clone or download this repository:

```bash
git clone https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite.git
```

### Step 2: Upload to WHMCS

Copy the module files to your WHMCS installation:

- `stripelite.php` → `/modules/gateways/stripelite.php`
- `callbacks/stripelite.php` → `/modules/gateways/callbacks/stripelite.php`

### Step 3: Activate in WHMCS Admin

1. Log in to WHMCS Admin Panel
2. Navigate to **Setup → Payments → Payment Gateways**
3. Click **All Payment Gateways**
4. Find and click on **Stripe Lite** to configure it

### Step 4: Configure API Keys

Select **Mode** (Test or Production):
- **Test Mode**: Use `pk_test_...` and `sk_test_...` keys
- **Production Mode**: Use `pk_live_...` and `sk_live_...` keys

Enter your API keys and save.

### Step 5: Configure Webhook (Optional but Recommended)

1. Go to Stripe Dashboard → **Developers → Webhooks**
2. Add endpoint: `https://yourdomain.com/modules/gateways/callbacks/stripelite.php`
3. Select events: `checkout.session.completed` and `payment_intent.succeeded`
4. Copy the **Webhook Signing Secret** and enter it in WHMCS settings

### Step 6: Test Your Setup

1. Create a test invoice in WHMCS
2. Select **Stripe Lite** at checkout
3. Use test card: `4242 4242 4242 4242`
4. Complete payment
5. Invoice should be marked as **Paid**

---

## Configuration Options

| Field | Description |
| --- | --- |
| **Mode** | Select Test (Sandbox) or Production (Live) |
| **Test Publishable Key** | Your Stripe test public key (pk_test_...) |
| **Test Secret Key** | Your Stripe test secret key (sk_test_...) |
| **Production Publishable Key** | Your Stripe live public key (pk_live_...) |
| **Production Secret Key** | Your Stripe live secret key (sk_live_...) |
| **Webhook Signing Secret** | Webhook signing secret (whsec_...) for webhook verification |

---

## How It Works

1. Customer selects "Stripe Lite" at checkout
2. Module creates Stripe Checkout Session via API
3. Customer is redirected to Stripe Checkout (secure page)
4. Customer completes payment on Stripe
5. Module verifies payment with Stripe API
6. Invoice is immediately marked as Paid in WHMCS
7. Webhook fallback (optional): If customer doesn't return, webhook confirms payment

---

## Duplicate Prevention

The module prevents duplicate payments by:
- Checking if transaction ID already exists
- Using Stripe Payment Intent ID as unique identifier
- Handling both redirect and webhook payments safely

---

## Requirements

- WHMCS v8.0 or higher (tested on v8.13)
- PHP 7.4 or newer
- Stripe account with API keys
- HTTPS (SSL) enabled
- cURL PHP extension

---

## Security

- No card data stored on your server
- All payments happen on Stripe's secure domain
- Webhook signature verification (HMAC-SHA256)
- Replay attack prevention (timestamp validation)
- PCI compliance handled by Stripe

---

## Troubleshooting

### Invoice Not Marked as Paid
- Check `/logs/stripe_lite.log` for errors
- Verify API keys are correct for the selected mode
- Ensure HTTPS is enabled

### Webhook Not Working
- Verify Webhook Signing Secret is entered in settings
- Check Stripe Dashboard for webhook delivery status
- Verify webhook endpoint URL is reachable

---

## Support

- [GitHub Issues](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/issues)
- [GitHub Discussions](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/discussions)

If helpful, please ⭐ star the repository!

---

## License

MIT License - See [LICENSE](LICENSE) file for details.

---

## Changelog

### v1.0.0 (November 2025)

- Initial public release
- Stripe Checkout redirect integration
- Manual payment verification via Stripe API
- Test Mode and Production Mode support
- Webhook fallback for payment confirmation
- Idempotent transaction handling (no duplicates)
- WHMCS 8.13 compatibility

---

_Created by ProgrammerNomad — for developers who value trust, transparency, and simplicity in online payments 💙_
