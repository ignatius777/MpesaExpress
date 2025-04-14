# WooCommerce M-Pesa STK Push Gateway (Daraja)

A private WooCommerce payment gateway plugin integrating M-Pesa STK Push using Safaricom's Daraja API.

> 🚧 **This plugin is under active development and is not yet ready for public use.**

---

## 🔧 Features (Planned / Implemented)

- ✅ Initiates STK Push on checkout using Daraja API
- ✅ Handles callback securely via `mpesa-callback.php`
- ✅ Stores M-Pesa responses in custom DB table (`mpesa_resp`)
- 🔄 WooCommerce order meta integration 
- 🔄 Auto order confirmation from DB (planned)

---

## 🚀 Installation (Development)

1. Clone this repository into `wp-content/plugins/`.
2. Activate the plugin via **WordPress > Plugins**.
3. Go to **WooCommerce > Settings > Payments** and enable **M-Pesa STK Push**.
4. Enter your Daraja credentials.

---

## ⚙️ Plugin Settings

In the gateway settings, enter the following:

- **Consumer Key**
- **Consumer Secret**
- **Shortcode**
- **Passkey**

---



