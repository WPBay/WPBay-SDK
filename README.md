# WPBay SDK

## Overview
The **WPBay SDK** is a powerful tool designed to help WordPress plugin and theme developers manage licensing, updates, and premium features through WPBay.com.

### Key Features:
- Sell and manage product licenses.
- Handle plugin/theme updates directly from WPBay.com.
- Enable premium features via license checks.
- Display custom admin pages for support, feedback, and more.
- Gather optional usage statistics.
- Show rating or upgrade notices.

## 🚀 Quick Integration Guide

1. **Download** the WPBay SDK and place it in your plugin or theme folder.
2. **Initialize** the SDK in your main file with your `api_key` from the WPBay Seller Dashboard.
3. **Configure** parameters like `is_free`, `is_upgradable`, and others.
4. **Test** using developer mode before going live.
5. **Go live!** Upload your product to WPBay and start selling.

📖 **Full Documentation:** [WPBay SDK Documentation](https://wpbay.com/wpbay-sdk-integration-documentation/)

---

## 📌 Requirements
- **WordPress Version:** 4.9+ (latest recommended)
- **PHP Version:** 7.0+

## 📥 Installation

1. **Download the SDK** from WPBay.
2. **Extract** and place the `wpbay-sdk/` folder inside your plugin or theme directory:
   ```bash
   my-plugin/
   ├── wpbay-sdk/
   │   ├── WPBay_Loader.php
   │   ├── ...
   ├── my-plugin.php
   └── ...
   ```
3. **Ensure compatibility**: The WPBay SDK automatically loads the latest version if multiple plugins use different versions.

## 🔧 Basic Integration

### 1️⃣ Include the WPBay SDK in Your Plugin/Theme

Add this to your main plugin file (`my-plugin.php`) or `functions.php` for themes:

```php
if ( ! function_exists( 'my_wpbay_sdk' ) ) {
    function my_wpbay_sdk() {
        require_once dirname( __FILE__ ) . '/wpbay-sdk/WPBay_Loader.php';
        
        global $wpbay_sdk_latest_loader;
        $sdk_loader_class = $wpbay_sdk_latest_loader;
        
        $sdk_params = array(
            'api_key'                 => '',
            'wpbay_product_id'        => '', 
            'product_file'            => __FILE__,
            'activation_redirect'     => '',
            'is_free'                 => false,
            'is_upgradable'           => false,
            'uploaded_to_wp_org'      => false,
            'disable_feedback'        => false,
            'disable_support_page'    => false,
            'disable_contact_form'    => false,
            'disable_upgrade_form'    => false,
            'disable_analytics'       => false,
            'rating_notice'           => '1 week',
            'debug_mode'              => false,
            'no_activation_required'  => false,
            'menu_data'               => array(
                'menu_slug' => ''
            ),
        );
        
        if ( class_exists( $sdk_loader_class ) ) {
            return $sdk_loader_class::load_sdk( $sdk_params );
        }
    }
    my_wpbay_sdk();
    do_action( 'my_wpbay_sdk_loaded' );
}
```

### 2️⃣ Debug Mode (Optional)
Enable debug logging:
```php
'sdk_params' => array(
    'debug_mode' => true,
);
```
Logs will be stored in `wp-content/wpbay_info.log`.

### 3️⃣ Developer Mode (Optional)
For local testing before uploading to WPBay:
```php
define( 'WPBAY_MY_PLUGIN_DEVELOPER_MODE', true );
define( 'WPBAY_MY_PLUGIN_SECRET_KEY', 'YOUR_SECRET_KEY' );
```
Set your testing slug in the **WPBay Seller Dashboard**.

### 4️⃣ Configure SDK Parameters

```php
$sdk_params = array(
    'api_key'                 => 'YOUR_API_KEY',
    'wpbay_product_id'        => 'YOUR_PRODUCT_ID',
    'product_file'            => __FILE__,
    'activation_redirect'     => 'options-general.php?page=wpbay-settings',
    'is_free'                 => false,
    'is_upgradable'           => false,
    'uploaded_to_wp_org'      => false,
    'disable_feedback'        => false,
    'disable_support_page'    => false,
    'disable_contact_form'    => false,
    'disable_upgrade_form'    => false,
    'disable_analytics'       => false,
    'debug_mode'              => false,
    'rating_notice'           => '1 week',
    'no_activation_required'  => false,
    'menu_data'               => array(
        'menu_slug' => 'my_plugin_admin_settings',
    ),
);
```

---

## 📂 Additional Features

### 🔹 Automatic Updates & License Verification
- **WPBay handles automatic updates** via the SDK.
- **License verification** ensures only valid customers can use premium features.

### 🔹 Contact & Upgrade Forms
You can manually add contact and upgrade forms to your plugin's settings page:

```php
$sdk_instance = my_wpbay_sdk();
$contact_manager = $sdk_instance->get_contact_form_manager();
if ($contact_manager) {
    $contact_manager->render_contact_form();
}
```

---

## ✅ External Services Disclosure

You should include the following section in the SDK’s own `readme.txt` or `readme.md` file. This ensures plugin authors and WordPress.org reviewers understand what data is sent and when — regardless of which plugin includes the SDK.

---

## == External Services ==

The WPBay SDK connects to external WPBay.com API services to handle license management, analytics, and optional feedback reporting.

The following API endpoints are used:

---

### = 1. License Management =

**URL:** `https://wpbay.com/api/purchase/v1/verify`  
Used to verify the validity of a purchase code and activate a license.

**Data sent:**

- Purchase code entered by the user  
- Site URL (`get_bloginfo('url')`)  
- API key provided to the plugin author  
- Developer mode and secret key (if configured)  
- Product slug and WPBay product ID  
- A cachebust token to prevent caching

**When it is sent:**

- When the plugin/theme is activated with a license code  
- Daily, via a scheduled license status check  
- When the license is manually verified or revoked

---

### = 2. Analytics Tracking (optional) =

**URL:** `https://wpbay.com/api/analytics/v1/submit`  
Used to submit anonymous usage and activation data (opt-in).

**Data sent:**

- Activation timestamp  
- Product slug and version  
- Site locale  
- License type and plan (if activated)  
- Plugin/theme context (e.g., plugin or theme)  
- **No personal user data is collected**

**When it is sent:**

- On first activation (if analytics is enabled)  
- Occasionally during plugin load or version updates

---

### = 3. Feedback Submission (optional) =

**URL:** `https://wpbay.com/api/feedback/v1/`  
Used when users submit feedback via an integrated feedback form.

**Data sent:**

- User-submitted message  
- Request type (bug, feature, support, etc.)  
- Site URL and product slug  
- User’s name and email (if entered)

**When it is sent:**

- Only when the user submits feedback through the plugin’s support form

---

## == Provider ==

All API services are provided by [WPBay](https://wpbay.com):

- [Terms of Service](https://wpbay.com/terms/)  
- [Privacy Policy](https://wpbay.com/privacy/)

> This SDK only sends data **after user consent**, when applicable, and includes opt-out options for analytics and feedback.

---

## ✅ Bonus: Add this as a PHP DocBlock

In your main `WPBay_SDK.php` or loader class, include this docblock to help both developers and automated tools recognize the SDK’s API usage:

```php
/**
 * This SDK integrates with external services provided by WPBay.com for license management,
 * optional analytics, and feedback submission. All usage is documented in the SDK's readme.txt.
 *
 * External services:
 * - https://wpbay.com/api/purchase/v1/
 * - https://wpbay.com/api/analytics/v1/submit
 * - https://wpbay.com/api/feedback/v1/
 *
 * Terms: https://wpbay.com/terms/
 * Privacy: https://wpbay.com/privacy/
 */
```

## ❓ FAQ

### Where do I get my `api_key` and `wpbay_product_id`?
👉 Log into WPBay.com and check your Seller Dashboard.

### Can I offer premium add-ons?
✅ Yes, with the `is_upgradable` flag enabled.

### Is WPBay SDK GPL-compliant?
✅ Yes, it follows GPL-compatible licensing.

### How can I get support?
📩 **Contact us at support [at] wpbay.com

---

## 📜 License
This SDK is open-source and follows the **GPL v2+ license**.

---

🔗 **For full documentation, visit:** [WPBay SDK Docs](https://wpbay.com/wpbay-sdk-integration-documentation/)
