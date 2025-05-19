=== PostTally ===
Contributors: coderevolution
Donate link: https://www.paypal.me/CodeRevolution
Tags: post count, shortcode, stats, utility
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin that adds a [post_count] shortcode to display the total number of published posts. It includes also the WPBay SDK to add additional features

== Description ==
**PostTally** is a lightweight WordPress plugin that provides a simple `[post_count]` shortcode. It displays the total number of published posts of the default "post" type, ideal for showcasing site activity in your content.

This plugin also integrates the **WPBay SDK**, which supports:
- Optional license activation and upgrade management
- Plugin auto-updates through WPBay.com
- Opt-in analytics and usage statistics
- Developer features like contact and support forms

The SDK is inactive by default unless configured. It only sends data if activated with a valid API key.

== Installation ==
1. Upload the `posttally` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Use the `[post_count]` shortcode anywhere in your content to display the total number of published posts.

== Frequently Asked Questions ==
= How do I use the plugin? =
Simply add the `[post_count]` shortcode to any post, page, or text widget.

= Does it count all post types? =
No, it only counts published posts of the default "post" type.

== Changelog ==
= 1.0.0 =
* Initial release.

== Upgrade Notice ==
= 1.0.0 =
Initial release, no upgrades yet.

== External Services ==

This plugin includes the WPBay SDK, which may connect to the following external services if activated (however, they are deactivated if the plugin is uploaded to wordpress.org):

= License Verification (optional) =
**https://wpbay.com/api/purchase/v1/verify**  
Used to verify purchase codes and manage licenses.

= Analytics (optional) =
**https://wpbay.com/api/analytics/v1/submit**  
Used for anonymous usage statistics, only if analytics are enabled.

= Feedback (optional) =
**https://wpbay.com/api/feedback/v1/**  
Used when submitting support or feedback forms through the plugin (if available).

These external services are only used if explicitly enabled by the plugin developer or user. No personal data is collected without user consent.

Provider: [WPBay](https://wpbay.com)  
Terms: https://wpbay.com/terms-and-conditions/
Privacy: https://wpbay.com/privacy-policy/