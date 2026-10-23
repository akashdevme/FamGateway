# FamGateway Payment Gateway Extension for Paymenter

Official **FamGateway** payment gateway extension for **Paymenter** — India's leading 100% Free, Non-Custodial Automated UPI Payment Gateway.

Accept automated UPI payments via **PhonePe, Google Pay, Paytm, CRED, FamPay, and BHIM** with **0.0% transaction fees** and direct P2P instant bank settlement to your account.

---

## ⚡ Features

- **0% Transaction Fees (Free Forever)**: 100% peer-to-peer (P2P), non-custodial automated UPI collection.
- **Instant Bank Settlement (0 Seconds)**: Funds go directly into your bank or FamPay account in real-time.
- **No GST or Current Account Required**: Fully compliant with Section 22 of the CGST Act, 2017 (exemption for indie developers, students, and businesses under ₹20 Lakhs/year).
- **Automated Provisioning**: Invoices are automatically marked as `paid` within 3–5 seconds upon UPI payment, instantly triggering server and service provisioning.
- **Bank-Grade Webhooks**: Built-in HMAC-SHA256 timing-safe cryptographic signature validation prevents spoofing and replay attacks.
- **Dynamic QR & Mobile UPI Intent**: Customers on desktop see an auto-updating QR code with live countdown timer; customers on mobile can tap to pay directly in their favorite UPI apps.

---

## 📂 File Structure

```text
FamGateway/
├── FamGateway.php               # Core Gateway Driver (implements getConfig, canUseGateway, pay)
├── routes.php                   # Webhook & client return routes
├── extension.json               # Extension metadata definition
├── Controllers/
│   └── FamGatewayController.php # HMAC-SHA256 signature verification & invoice fulfillment
└── README.md                    # Installation and configuration guide
```

---

## 🚀 Installation Guide

### Step 1: Copy Extension into Paymenter

Upload or copy the `FamGateway` directory into your Paymenter installation:

#### For Paymenter v0 (Standard):
```bash
cp -r FamGateway /var/www/paymenter/app/Extensions/Gateways/
```

#### For Paymenter v1:
```bash
cp -r FamGateway /var/www/paymenter/extensions/Gateways/
```

### Step 2: Set Proper File Permissions

Ensure your web server user (`www-data` or `nginx`) owns the files:
```bash
chown -R www-data:www-data /var/www/paymenter/app/Extensions/Gateways/FamGateway
chmod -R 755 /var/www/paymenter/app/Extensions/Gateways/FamGateway
```

### Step 3: Clear Laravel Cache

```bash
cd /var/www/paymenter
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

---

## ⚙️ Configuration in Admin Panel

1. Log in to your **Paymenter Admin Panel**.
2. Navigate to **Extensions** ➜ **Gateways** (or **Settings** ➜ **Payments**).
3. Find **FamGateway** and click **Enable / Install**.
4. Configure the settings:
   - **FamGateway API Key**: Paste your secret API key from the [FamGateway Merchant Dashboard](https://famgateway.in/dashboard) (e.g. `fam_afc0da5b20f89df571d7c758a0e1fb5a3d11df94`).
   - **Restrict Currency to INR**: Set to `true` (Enabled) since FamGateway processes Indian UPI payments.
   - **Gateway Display Name**: `UPI / FamPay / QR Code (Zero Fee)`
5. Click **Save Settings**.

---

## 🔗 Configure Webhook in FamGateway

1. Go to your [FamGateway Merchant Dashboard](https://famgateway.in/dashboard).
2. Set your default **Webhook URL** to:
   ```text
   https://your-paymenter-domain.com/extensions/gateways/famgateway/webhook
   ```
   *(Note: The extension also dynamically injects the `webhook_url` and `invoice_id` with each order for 100% fail-safe multi-invoice reconciliation).*

---

## 🔒 Security Architecture

1. **HMAC-SHA256 Webhook Verification**: Every incoming webhook payload is verified using `hash_equals(hash_hmac('sha256', $rawBody, $apiKey), $signature)` to ensure requests originate exclusively from FamGateway.
2. **CSRF Exemption**: Webhook route is safely excluded from Laravel's CSRF token verification without compromising other routes.
3. **Idempotent Settlement**: Prevents duplicate provisioning or billing race conditions.

---

## 📞 Support & Community

- **Official Website**: [https://famgateway.in](https://famgateway.in)
- **Developer Documentation**: [https://famgateway.in/docs.md](https://famgateway.in/docs.md)
- **Live Status**: [https://famgateway.in/status.php](https://famgateway.in/status.php)
- **Telegram Developer Community**: [https://t.me/aryanispe_related](https://t.me/aryanispe_related)
- **WhatsApp Direct Support**: [+91 97713 48544](https://wa.me/919771348544)
