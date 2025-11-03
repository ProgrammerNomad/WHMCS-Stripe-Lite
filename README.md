# 🟦 WHMCS Stripe Lite# WHMCS Stripe Lite



[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[![WHMCS](https://img.shields.io/badge/WHMCS-v8.13-success)](https://www.whmcs.com/)[![WHMCS](https://img.shields.io/badge/WHMCS-Compatible-success)](https://www.whmcs.com/)

[![Stripe](https://img.shields.io/badge/Stripe-Checkout-blueviolet.svg)](https://stripe.com/)[![Stripe](https://img.shields.io/badge/Stripe-Checkout-blueviolet.svg)](https://stripe.com/)

[![GitHub Repo](https://img.shields.io/badge/GitHub-ProgrammerNomad%2FWHMCS--Stripe--Lite-black)](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite)[![GitHub Repo](https://img.shields.io/badge/GitHub-ProgrammerNomad%2FWHMCS--Stripe--Lite-black)](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite)



> A lightweight, trust-focused Stripe payment gateway for WHMCS that redirects customers to Stripe's official Checkout page for secure payments.# 🟦 WHMCS Stripe Lite



---[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[![WHMCS](https://img.shields.io/badge/WHMCS-v8.13-success)](https://www.whmcs.com/)

## Overview[![Stripe](https://img.shields.io/badge/Stripe-Checkout-blueviolet.svg)](https://stripe.com/)

[![GitHub Repo](https://img.shields.io/badge/GitHub-ProgrammerNomad%2FWHMCS--Stripe--Lite-black)](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite)

**WHMCS Stripe Lite** is a simplified and trust-oriented alternative to the official WHMCS Stripe module. Rather than collecting card details on your site, Stripe Lite redirects customers to Stripe Checkout — a Stripe-hosted, PCI-compliant payment page — increasing security and customer trust while preserving WHMCS automation.

> A lightweight, trust-focused Stripe payment gateway for WHMCS that redirects customers to Stripe's official Checkout page for secure payments.

This reduces friction for customers who are hesitant to enter card details on new or unfamiliar sites and helps lower cart abandonment.

---

---

## Overview

## Features

**WHMCS Stripe Lite** is a simplified and trust-oriented alternative to the official WHMCS Stripe module. Rather than collecting card details on your site, Stripe Lite redirects customers to Stripe Checkout — a Stripe-hosted, PCI-compliant payment page — increasing security and customer trust while preserving WHMCS automation.

- Seamless integration with WHMCS v8.0+

- Redirects customers to Stripe Checkout (no card input on your server)This reduces friction for customers who are hesitant to enter card details on new or unfamiliar sites and helps lower cart abandonment.

- Matches the core functionality of the official Stripe gateway

- Improves customer trust and conversions---

- Easy setup and configuration

- Supports all currencies and payment methods provided by Stripe## Features

- **Dual Mode Support**: Test (Sandbox) and Production (Live) modes with separate API keys

- **Manual Payment Verification**: Verifies payment status directly from Stripe API upon redirect- Seamless integration with WHMCS v8.0+

- **Idempotent Transaction Handling**: Prevents duplicate invoice payments- Redirects customers to Stripe Checkout (no card input on your server)

- **Webhook Fallback**: Automatic payment confirmation if customer doesn't redirect back to WHMCS- Matches the core functionality of the official Stripe gateway

- Improves customer trust and conversions

---- Easy setup and configuration

- Supports all currencies and payment methods provided by Stripe

## Installation- **Dual Mode Support**: Test (Sandbox) and Production (Live) modes with separate API keys

- **Manual Payment Verification**: Verifies payment status directly from Stripe API upon redirect

### Step 1: Download the Module- **Idempotent Transaction Handling**: Prevents duplicate invoice payments

- **Webhook Fallback**: Automatic payment confirmation if customer doesn't redirect back to WHMCS

Clone or download this repository:

---

```powershell

git clone https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite.git## Overview

```

**WHMCS Stripe Lite** is a simplified and trust-oriented alternative to the official WHMCS Stripe module. Rather than collecting card details on your site, Stripe Lite redirects customers to Stripe Checkout - a Stripe-hosted, PCI-compliant payment page - increasing security and customer trust while preserving WHMCS automation.

### Step 2: Upload to WHMCS

This reduces friction for customers who are hesitant to enter card details on new or unfamiliar sites and helps lower cart abandonment.

Copy the module files to your WHMCS installation:

---

- `stripelite.php` → `/modules/gateways/stripelite.php`

- `callbacks/stripelite.php` → `/modules/gateways/callbacks/stripelite.php`## Features



**Folder Structure:**- Seamless integration with WHMCS

- Redirects customers to Stripe Checkout (no card input on your server)

```text- Matches the core functionality of the official Stripe gateway

<whmcs-root>/- Improves customer trust and conversions

├── modules/- Easy setup and configuration

│   └── gateways/- Supports all currencies and payment methods provided by Stripe

│       ├── stripelite.php                 (main gateway file)

│       └── callbacks/---

│           └── stripelite.php             (webhook handler)

```## Installation



### Step 3: Activate in WHMCS Admin1. Download or clone this repository:



1. Log in to WHMCS Admin Panel```powershell

2. Navigate to **Setup → Payments → Payment Gateways**git clone https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite.git

3. Click **All Payment Gateways**```

4. Find and click on **Stripe Lite** to configure it

2. Copy the module folder into your WHMCS installation gateways directory so the files live at:

### Step 4: Configure API Keys

```text

The gateway provides a **Mode selector** at the top with two options: **Test Mode (Sandbox)** and **Production Mode (Live)**.<whmcs-root>/modules/gateways/stripelite/

```

#### Test Mode Setup (Sandbox)

3. In WHMCS Admin: Setup → Payments → Payment Gateways → All Payment Gateways

1. Select **"Test Mode (Sandbox)"** from the Mode dropdown

2. Enter your **Test Publishable Key** (starts with `pk_test_`)4. Find and activate “Stripe Lite”.

3. Enter your **Test Secret Key** (starts with `sk_test_`)

4. Click **Save**5. Enter your Stripe API keys in the module settings:



#### Production Mode Setup (Live)- Publishable Key (pk_...)

- Secret Key (sk_...)

1. Select **"Production Mode (Live)"** from the Mode dropdown

2. Enter your **Production Publishable Key** (starts with `pk_live_`)6. Save settings. You can enable Test Mode to use Stripe test keys while verifying integration.

3. Enter your **Production Secret Key** (starts with `sk_live_`)

4. Click **Save**---



**To obtain your API keys:**## Configuration Options

- Log in to your [Stripe Dashboard](https://dashboard.stripe.com/)

- Go to **Developers → API Keys**| Option | Description |

- Copy your Publishable and Secret keys for the mode you're setting up| --- | --- |

| Test Mode | Use Stripe test API keys for sandbox testing |

### Step 5: Configure Webhook (Recommended for Fallback)| Webhook Support | Recommended - verifies payments and updates WHMCS invoices automatically |

| Currency Support | Works with any currency your Stripe account supports |

To enable automatic payment confirmation in case the customer doesn't return to WHMCS after completing payment:| Redirect URL | Payments go to [Stripe Checkout](https://checkout.stripe.com/) (Stripe-hosted) |



1. In WHMCS Stripe Lite settings, scroll down and enter your **Webhook Signing Secret**---

2. In your [Stripe Dashboard](https://dashboard.stripe.com/), go to **Developers → Webhooks**

3. Click **Add endpoint** and enter: `https://yourdomain.com/modules/gateways/callbacks/stripelite.php`## How it works

4. Select the following events to listen for:

   - `checkout.session.completed`1. Customer selects “Stripe Lite” at checkout.

   - `payment_intent.succeeded` (optional, as backup)2. WHMCS sends the invoice/transaction data to the module.

5. After creating the endpoint, copy the **Signing Secret** (starts with `whsec_`)3. The module creates a Stripe Checkout Session and redirects the customer to Stripe’s hosted page.

6. Paste it into the WHMCS Stripe Lite **Webhook Signing Secret** field4. Customer completes payment on Stripe Checkout.

7. Click **Save** in WHMCS5. Stripe sends a webhook to your WHMCS site (recommended) to confirm payment.

6. The module marks the invoice as paid and continues WHMCS automation.

### Step 6: Test Your Setup

---

1. Create a test invoice in WHMCS

2. Go to checkout and select **Stripe Lite** as the payment method## Requirements

3. You will be redirected to Stripe Checkout

4. Use Stripe test card: `4242 4242 4242 4242`, any future expiry date (e.g., 12/25), and any 3-digit CVC- WHMCS v8.0 or higher

5. Complete the payment- PHP 7.4 or newer (check your WHMCS recommended PHP version)

6. You should be redirected back to WHMCS and the invoice should be marked as **Paid**- A Stripe account with API keys (test and live)

- HTTPS (SSL) on your WHMCS installation (required for webhooks and security)

---

---

## Configuration Options

## Security

| Field | Description |

| --- | --- |- No card data is collected or stored on your server - all card entry happens on Stripe’s domain.

| **Mode** | Select "Test Mode (Sandbox)" for testing or "Production Mode (Live)" for real payments |- Stripe handles PCI compliance for the hosted Checkout flow.

| **Test Publishable Key** | Your Stripe test public key (pk_test_...) |- Webhook signature verification is used to validate incoming Stripe notifications.

| **Test Secret Key** | Your Stripe test secret key (sk_test_...) - Keep this secure! |

| **Production Publishable Key** | Your Stripe live public key (pk_live_...) |For security, ensure your WHMCS site uses HTTPS and your webhook endpoint URL/secret is kept confidential.

| **Production Secret Key** | Your Stripe live secret key (sk_live_...) - Keep this secure! |

| **Webhook Signing Secret** | Your webhook signing secret (whsec_...) for validating webhook events |---



---## Webhooks (recommended)



## How It WorksTo automatically mark invoices paid you should configure a Stripe webhook pointing to your WHMCS webhook endpoint (e.g., `https://yourdomain.com/modules/gateways/callback/stripelite.php`).



### Payment FlowEvents to listen for:



1. **Customer Initiates Payment**: Customer selects "Stripe Lite" at checkout- `checkout.session.completed`

2. **Session Creation**: WHMCS module sends invoice details to Stripe API and creates a Checkout Session- `payment_intent.succeeded` (optional, depending on flow)

3. **Redirect to Stripe**: Customer is redirected to Stripe's secure Checkout page

4. **Complete Payment**: Customer enters card details and completes payment on Stripe's domain (not your server)Use the webhook signing secret from the Stripe Dashboard to configure the module so it can verify incoming webhook requests.

5. **Return with Verification**: Stripe redirects customer back to WHMCS with a session ID

6. **Automatic Verification**: Module verifies payment status by querying Stripe API---

7. **Invoice Marked as Paid**: Upon successful verification, invoice is immediately marked as paid in WHMCS

8. **Webhook Fallback**: If customer doesn't return, Stripe webhook ensures payment is still recorded (idempotent)## Testing



### Duplicate Prevention1. Activate Test Mode and use Stripe test API keys.

2. Create a test invoice in WHMCS and pay with the test card numbers provided by Stripe (e.g., `4242 4242 4242 4242`).

The module ensures no duplicate transactions by:3. Verify the webhook delivers and the invoice is marked paid.

- Checking if a transaction with the same ID already exists before adding a new one

- Using the Stripe Payment Intent ID as the transaction ID for idempotency---

- Safely handling both immediate payment returns and webhook events

## Versioning

---

| Version | Notes |

## Webhooks (Recommended Fallback)| --- | --- |

| v1.0.0 | Initial release - core Stripe Checkout redirect functionality |

Webhooks act as a safety net in case the customer doesn't return to WHMCS after completing payment:

---

### Events Handled

## Support & Contribution

- **`checkout.session.completed`**: Primary event - triggered when a checkout session is completed and paid

- **`payment_intent.succeeded`** (optional): Backup event - triggered when a payment intent succeedsContributions are welcome. Please open an issue for bugs or feature requests and submit pull requests for improvements.



### Configuration in Stripe Dashboard- [Open an issue](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/issues)

- [Submit a Pull Request](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/pulls)

1. Go to **Developers → Webhooks** in your Stripe Dashboard

2. Click **Add endpoint**If you find this module helpful, a ⭐ on the repository is appreciated - it helps fund future updates.

3. Enter your WHMCS webhook URL: `https://yourdomain.com/modules/gateways/callbacks/stripelite.php`

4. Select the events above---

5. Click **Add endpoint**

6. Copy the **Signing Secret** and paste it in WHMCS settings## License



### Important NotesThis project is licensed under the MIT License - see the `LICENSE` file for details.



- Webhooks are **recommended but optional** — payments are verified immediately upon redirect---

- The module prevents duplicate invoices even if both redirect and webhook trigger

- Webhook failures are logged to `/logs/stripe_lite.log`Created by NomadProgrammer - for developers who value trust, transparency, and simplicity in online payments 💙


---

## Testing

### Test Mode Testing

1. Ensure **Mode** is set to **"Test Mode (Sandbox)"** in gateway settings
2. Use Stripe test API keys (starting with `pk_test_` and `sk_test_`)
3. Create a test invoice in WHMCS
4. Process payment with test card **`4242 4242 4242 4242`**
   - **Expiry:** Any future date (e.g., 12/25)
   - **CVC:** Any 3 digits (e.g., 123)
5. Verify the invoice is marked **Paid** in WHMCS

### Webhook Testing (Optional)

1. Use Stripe CLI to test webhooks locally (optional)
2. Or complete a test payment and wait a few seconds to ensure webhook confirms it
3. Check `/logs/stripe_lite.log` for transaction logs

### Production Mode Testing

1. Switch to **"Production Mode (Live)"** when ready
2. Use real Stripe API keys (starting with `pk_live_` and `sk_live_`)
3. Process real payments and verify they appear in WHMCS
4. **Caution:** Real charges will be processed!

---

## Troubleshooting

### Invoice Not Marked as Paid

**Check:**
1. Verify API keys are correct (Test vs. Live mode match)
2. Check logs at `/logs/stripe_lite.log` for error messages
3. Ensure HTTPS is enabled on your WHMCS installation
4. Verify Stripe account has payment processing enabled

### Webhook Not Working

**Check:**
1. Confirm Webhook Signing Secret is entered in WHMCS settings
2. Check Stripe Dashboard → Developers → Webhooks for delivery failures
3. Verify webhook endpoint URL is reachable (test with browser)
4. Check `/logs/stripe_lite.log` for webhook errors

### Duplicate Charges

The module prevents duplicate charges through transaction ID validation. If you suspect duplicates:
1. Check WHMCS → Invoices for the transaction
2. Review Stripe Dashboard for actual charges
3. Contact support with transaction IDs

---

## Requirements

- **WHMCS:** v8.0 or higher (tested on v8.13)
- **PHP:** 7.4 or newer
- **Stripe Account:** Active account with API keys available
- **HTTPS:** SSL certificate required (especially for webhooks)
- **cURL:** PHP cURL extension enabled (for API calls)

---

## Security

- **No Card Data on Your Server**: All card details are entered on Stripe's secure domain
- **PCI Compliance**: Stripe handles all PCI compliance requirements for you
- **Webhook Signature Verification**: All webhook events are verified using Stripe's signing secret
- **Timestamp Validation**: Webhook events are validated to prevent replay attacks
- **HTTPS Required**: Use HTTPS/SSL on your WHMCS installation for security and webhooks

**Best Practices:**
- Keep your Secret Keys (both Test and Production) confidential
- Use a strong Webhook Signing Secret
- Regularly review payment transactions in WHMCS and Stripe Dashboard
- Monitor your logs at `/logs/stripe_lite.log` for any issues

---

## Versioning

| Version | Release Date | Notes |
| --- | --- | --- |
| v1.0.0 | November 2025 | Initial release - Stripe Checkout redirect with manual verification, webhook fallback, dual mode support |

---

## Support & Contribution

Issues, questions, or suggestions? We're here to help!

- [Open an Issue](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/issues)
- [Submit a Pull Request](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/pulls)

If you find this module helpful, a ⭐ on the repository is appreciated — it helps fund future updates and improvements.

---

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

Free to use, modify, and distribute.

---

## Changelog

### v1.0.0 (November 2025)

- ✅ Initial public release
- ✅ Stripe Checkout redirect integration
- ✅ Manual payment verification via Stripe API
- ✅ Test Mode (Sandbox) and Production Mode (Live) support
- ✅ Webhook fallback for payment confirmation
- ✅ Idempotent transaction handling (no duplicates)
- ✅ WHMCS 8.13 compatibility
- ✅ Comprehensive logging

---

_Created by ProgrammerNomad — for developers who value trust, transparency, and simplicity in online payments 💙_

**Support WHMCS Stripe Lite by giving us a ⭐ on GitHub!**
