# WHMCS Stripe Lite

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![WHMCS](https://img.shields.io/badge/WHMCS-Compatible-success)](https://www.whmcs.com/)
[![Stripe](https://img.shields.io/badge/Stripe-Checkout-blueviolet.svg)](https://stripe.com/)
[![GitHub Repo](https://img.shields.io/badge/GitHub-ProgrammerNomad%2FWHMCS--Stripe--Lite-black)](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite)

> A lightweight, trust-focused Stripe payment gateway for WHMCS that redirects customers to Stripe’s official Checkout page for secure payments.

---

## Overview

**WHMCS Stripe Lite** is a simplified and trust-oriented alternative to the official WHMCS Stripe module. Rather than collecting card details on your site, Stripe Lite redirects customers to Stripe Checkout — a Stripe-hosted, PCI-compliant payment page — increasing security and customer trust while preserving WHMCS automation.

This reduces friction for customers who are hesitant to enter card details on new or unfamiliar sites and helps lower cart abandonment.

---

## Features

- Seamless integration with WHMCS
- Redirects customers to Stripe Checkout (no card input on your server)
- Matches the core functionality of the official Stripe gateway
- Improves customer trust and conversions
- Easy setup and configuration
- Supports all currencies and payment methods provided by Stripe

---

## Installation

1. Download or clone this repository:

```powershell
git clone https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite.git
```

2. Copy the module folder into your WHMCS installation gateways directory so the files live at:

```text
<whmcs-root>/modules/gateways/stripe_lite/
```

3. In WHMCS Admin: Setup → Payments → Payment Gateways → All Payment Gateways

4. Find and activate “Stripe Lite”.

5. Enter your Stripe API keys in the module settings:

- Publishable Key (pk_...)
- Secret Key (sk_...)

6. Save settings. You can enable Test Mode to use Stripe test keys while verifying integration.

---

## Configuration Options

| Option | Description |
| --- | --- |
| Test Mode | Use Stripe test API keys for sandbox testing |
| Webhook Support | Recommended — verifies payments and updates WHMCS invoices automatically |
| Currency Support | Works with any currency your Stripe account supports |
| Redirect URL | Payments go to [Stripe Checkout](https://checkout.stripe.com/) (Stripe-hosted) |

---

## How it works

1. Customer selects “Stripe Lite” at checkout.
2. WHMCS sends the invoice/transaction data to the module.
3. The module creates a Stripe Checkout Session and redirects the customer to Stripe’s hosted page.
4. Customer completes payment on Stripe Checkout.
5. Stripe sends a webhook to your WHMCS site (recommended) to confirm payment.
6. The module marks the invoice as paid and continues WHMCS automation.

---

## Requirements

- WHMCS v8.0 or higher
- PHP 7.4 or newer (check your WHMCS recommended PHP version)
- A Stripe account with API keys (test and live)
- HTTPS (SSL) on your WHMCS installation (required for webhooks and security)

---

## Security

- No card data is collected or stored on your server — all card entry happens on Stripe’s domain.
- Stripe handles PCI compliance for the hosted Checkout flow.
- Webhook signature verification is used to validate incoming Stripe notifications.

For security, ensure your WHMCS site uses HTTPS and your webhook endpoint URL/secret is kept confidential.

---

## Webhooks (recommended)

To automatically mark invoices paid you should configure a Stripe webhook pointing to your WHMCS webhook endpoint (e.g., `https://yourdomain.com/modules/gateways/callback/stripe_lite.php`).

Events to listen for:

- `checkout.session.completed`
- `payment_intent.succeeded` (optional, depending on flow)

Use the webhook signing secret from the Stripe Dashboard to configure the module so it can verify incoming webhook requests.

---

## Testing

1. Activate Test Mode and use Stripe test API keys.
2. Create a test invoice in WHMCS and pay with the test card numbers provided by Stripe (e.g., `4242 4242 4242 4242`).
3. Verify the webhook delivers and the invoice is marked paid.

---

## Versioning

| Version | Notes |
| --- | --- |
| v1.0.0 | Initial release — core Stripe Checkout redirect functionality |

---

## Support & Contribution

Contributions are welcome. Please open an issue for bugs or feature requests and submit pull requests for improvements.

- [Open an issue](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/issues)
- [Submit a Pull Request](https://github.com/ProgrammerNomad/WHMCS-Stripe-Lite/pulls)

If you find this module helpful, a ⭐ on the repository is appreciated — it helps fund future updates.

---

## License

This project is licensed under the MIT License — see the `LICENSE` file for details.

---

Created by ProgrammerNomad — for developers who value trust, transparency, and simplicity in online payments 💙
