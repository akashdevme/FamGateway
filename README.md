# FamGateway Payment Gateway Extension for Paymenter

Official **FamGateway** payment gateway extension for **Paymenter** — India's leading 100% Free, Non-Custodial Automated UPI Payment Gateway.

> **Developer Credits**: Developed by **taravspie**  
> **Merchant Dashboard**: [https://famgateway.in/dashboard.php](https://famgateway.in/dashboard.php)  
> **Official Website**: [https://famgateway.in](https://famgateway.in)  

Accept automated UPI payments via **PhonePe, Google Pay, Paytm, CRED, FamPay, and BHIM** with **0.0% transaction fees** and direct P2P instant bank settlement to your account.

---

## ⚡ Features

- **Developed by taravspie**: Complete, native Paymenter extension for zero-fee UPI collections.
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
├── FamGateway.php               # Core Gateway Driver (by taravspie)
├── routes/
│   └── web.php                  # Webhook route registration
├── extension.json               # Extension metadata definition with developer credits
└── README.md                    # Installation and configuration guide
```

---

## 🚀 Installation on Your VPS

1. Clone directly into Paymenter's gateways directory:
   ```bash
   cd /var/www/paymenter/extensions/Gateways
   git clone https://github.com/akashdevme/FamGateway.git FamGateway
   chown -R www-data:www-data FamGateway
   chmod -R 755 FamGateway
   ```

2. Clear Laravel cache:
   ```bash
   cd /var/www/paymenter
   php artisan route:clear
   php artisan config:clear
   php artisan cache:clear
   ```

3. Enable in Admin Dashboard:
   - Go to **Admin** ➜ **Extensions** ➜ **Gateways** ➜ **FamGateway**.
   - Enter your API Key from [FamGateway Dashboard](https://famgateway.in/dashboard.php).
   - Set the Webhook URL in your FamGateway dashboard to:
     ```text
     https://your-domain.com/extensions/gateways/famgateway/webhook
     ```

---

## 📞 Support & Community

- **Merchant Dashboard**: [https://famgateway.in/dashboard.php](https://famgateway.in/dashboard.php)
- **Developer Documentation**: [https://famgateway.in/docs.md](https://famgateway.in/docs.md)
- **Developer**: taravspie
