<?php
/**
 * @package     WPBay
 * @copyright   Copyright (c) 2025, WPBay
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.0.0
 *
 * This SDK integrates with external services provided by WPBay.com for license management,
 * optional analytics, and feedback submission. All usage is documented in the SDK's readme.md.
 *
 * == External Services Used ==
 *
 * 1. License Management
 *    - Endpoint: https://wpbay.com/api/purchase/v1/verify
 *    - Purpose: Verifies and activates purchase codes.
 *    - Data Sent:
 *        - Purchase code
 *        - Site URL (via get_bloginfo('url'))
 *        - API key and secret key
 *        - Developer mode flag
 *        - Product slug & ID
 *        - Cache busting token
 *    - Triggered: on activation, daily cron, or manual license validation.
 *
 * 2. Analytics Tracking (Optional / Opt-in)
 *    - Endpoint: https://wpbay.com/api/analytics/v1/submit
 *    - Purpose: Collects anonymized usage data to help developers improve their products.
 *    - Data Sent:
 *        - Product slug and version
 *        - Site locale
 *        - Activation timestamp
 *        - Plan and license type (if available)
 *        - Context (plugin or theme)
 *    - Triggered: on activation or update, if analytics is enabled.
 *
 * 3. Feedback Submission (Optional)
 *    - Endpoint: https://wpbay.com/api/feedback/v1/
 *    - Purpose: Allows users to send support messages, bug reports, or feature requests.
 *    - Data Sent:
 *        - Name and email (if submitted)
 *        - Request type
 *        - Message content
 *        - Product slug and site URL
 *    - Triggered: when the user submits the feedback form.
 *
 * == Privacy & Compliance ==
 * Terms: https://wpbay.com/terms-and-conditions/
 * Privacy: https://wpbay.com/privacy-policy/
 *
 * Note: No personal information is sent without user consent. Analytics and feedback features are disabled by default and can be turned off using SDK parameters.
 */

namespace WPBaySDK;

global $wpbay_sdk_active_plugins;
global $wpbay_sdk_version;
global $wpbay_sdk_latest_loader;
global $wp_version;

$wpbay_sdk_version = '1.0.7'; 

if (!class_exists( 'WPBaySDK\WPBay_SDK')) 
{
    require_once dirname( __FILE__ ) . '/WPBay_SDK.php';
}
if (!function_exists( 'wpbay_sdk_create_secure_nonce')) 
{
    require_once dirname( __FILE__ ) . '/WPBay_Helpers.php';
}

$file_path    = wpbay_sdk_normalize_path( __FILE__ );
$wpbay_sdk_root_path = dirname( $file_path );

//fix for a WordPress 6.3 bug
if (
    ! function_exists( 'wp_get_current_user' ) &&
    version_compare( $wp_version, '6.3', '>=' ) &&
    version_compare( $wp_version, '6.3.1', '<=' ) &&
    (
        isset($_SERVER['SCRIPT_FILENAME']) && 'site-editor.php' === basename( sanitize_text_field(wp_unslash($_SERVER['SCRIPT_FILENAME'])) ) ||
        (
            function_exists( 'wp_is_json_request' ) &&
            wp_is_json_request() &&
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Only checking existence of $_GET key, no trust or action taken
            isset($_GET['wp_theme_preview']) && ! empty( $_GET['wp_theme_preview'] ) 
        )
    )
) 
{
    // phpcs:ignore WordPress.Files.FileInclude.FileInclude — This include is conditional and only used to prevent errors on early execution. Reason: Ensures access to pluggable functions like wp_get_current_user() in rare edge cases (e.g., WP < 3.0 theme previews).
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

//theme or plugin detection
$themes_directory         = get_theme_root( get_stylesheet() );
$themes_directory_name    = basename( $themes_directory );
$theme_candidate_basename = basename( dirname( $wpbay_sdk_root_path ) ) . '/' . basename( $wpbay_sdk_root_path );
if ( $file_path == wpbay_sdk_normalize_path( realpath( trailingslashit( $themes_directory ) . $theme_candidate_basename . '/' . basename( $file_path ) ) )
) {
    $this_sdk_relative_path = '../' . $themes_directory_name . '/' . $theme_candidate_basename;
    $wpbay_sdk_is_theme               = true;
} else {
    $this_sdk_relative_path = plugin_basename( $wpbay_sdk_root_path );
    $wpbay_sdk_is_theme               = false;
}

if ( ! isset( $wpbay_sdk_active_plugins ) ) {
    $wpbay_sdk_active_plugins = get_option( 'wpbay_sdk_active_plugins', new \stdClass() );
    
    if ( ! isset( $wpbay_sdk_active_plugins->plugins ) ) {
        if(!is_object($wpbay_sdk_active_plugins))
        {
            $wpbay_sdk_active_plugins = new \stdClass();
        }
        $wpbay_sdk_active_plugins->plugins = array();
    }
}

if ( empty( $wpbay_sdk_active_plugins->abspath ) || ABSPATH !== $wpbay_sdk_active_plugins->abspath ) {
    $wpbay_sdk_active_plugins->abspath = ABSPATH;
    $wpbay_sdk_active_plugins->plugins = array(); 
    unset( $wpbay_sdk_active_plugins->newest ); 
} else {
    $has_changes = false;
    
    foreach ( $wpbay_sdk_active_plugins->plugins as $sdk_path => $data ) {
        $directory = isset( $data->type ) && $data->type === 'theme' ? $themes_directory : WP_PLUGIN_DIR;
        
        if ( ! file_exists( $directory . '/' . $sdk_path ) ) {
            unset( $wpbay_sdk_active_plugins->plugins[ $sdk_path ] );
            
            if ( ! empty( $wpbay_sdk_active_plugins->newest ) && $sdk_path === $wpbay_sdk_active_plugins->newest->sdk_path ) {
                unset( $wpbay_sdk_active_plugins->newest );
            }
            $has_changes = true;
        }
    }

    if ( $has_changes ) {
        if ( empty( $wpbay_sdk_active_plugins->plugins ) ) {
            unset( $wpbay_sdk_active_plugins->newest );
        }
        update_option( 'wpbay_sdk_active_plugins', $wpbay_sdk_active_plugins, false );
    }
}
if ( ! isset( $wpbay_sdk_active_plugins->plugins[ $this_sdk_relative_path ] ) || 
$wpbay_sdk_version !== $wpbay_sdk_active_plugins->plugins[ $this_sdk_relative_path ]->version ) 
{
    $plugin_path = $wpbay_sdk_is_theme ? basename( dirname( $this_sdk_relative_path ) ) 
                            : plugin_basename( wpbay_sdk_find_direct_caller_plugin_file( $file_path ) );
    $wpbay_sdk_active_plugins->plugins[ $this_sdk_relative_path ] = (object) [
    'version'     => $wpbay_sdk_version,
    'type'        => $wpbay_sdk_is_theme ? 'theme' : 'plugin',
    'timestamp'   => time(),
    'plugin_path' => $plugin_path,
    ];
}
$is_current_sdk_newest = ! empty( $wpbay_sdk_active_plugins->newest ) && 
$this_sdk_relative_path === $wpbay_sdk_active_plugins->newest->sdk_path;

if ( ! isset( $wpbay_sdk_active_plugins->newest ) ) 
{
    wpbay_sdk_update_sdk_newest_version( $this_sdk_relative_path, $wpbay_sdk_active_plugins->plugins[ $this_sdk_relative_path ]->plugin_path );
    $is_current_sdk_newest = true;
} 
elseif ( version_compare( $wpbay_sdk_active_plugins->newest->version, $wpbay_sdk_version, '<' ) ) 
{
    if(!empty($wpbay_sdk_active_plugins->plugins[ $this_sdk_relative_path ]->plugin_path))
    {
        wpbay_sdk_update_sdk_newest_version( $this_sdk_relative_path, $wpbay_sdk_active_plugins->plugins[ $this_sdk_relative_path ]->plugin_path );
        if ( class_exists( 'WPBaySDK\WPBay_SDK' ) && !defined('WP_FS__SDK_VERSION') ) {
            if ( ! $wpbay_sdk_active_plugins->newest->in_activation ) {
                if(wpbay_sdk_newest_sdk_plugin_first())
                {
                    $last_redirect = get_transient('wpbay_sdk_redirect_timestamp');
                    $current_time = time();
                    if (isset($_SERVER['REQUEST_URI']) && (!$last_redirect || ($current_time - $last_redirect > 10))) 
                    {
                        set_transient('wpbay_sdk_redirect_timestamp', $current_time, 10);
                        wpbay_sdk_redirect( sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) );
                    }
                }
            }
        }
    }
}
else 
{
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $wpbay_sdk_newest_sdk = $wpbay_sdk_active_plugins->newest;
    $wpbay_sdk_newest_sdk = $wpbay_sdk_active_plugins->plugins[ $wpbay_sdk_newest_sdk->sdk_path ];

    $is_newest_sdk_type_theme = ( isset( $wpbay_sdk_newest_sdk->type ) && 'theme' === $wpbay_sdk_newest_sdk->type );

    if ( ! $is_newest_sdk_type_theme ) {
        if(!empty($wpbay_sdk_newest_sdk->plugin_path))
        {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $is_newest_sdk_plugin_active = is_plugin_active( $wpbay_sdk_newest_sdk->plugin_path );
        }
        else
        {
            $is_newest_sdk_plugin_active = false;
        }
    } else {
        if(!empty($wpbay_sdk_newest_sdk->plugin_path))
        {
            $current_theme = wp_get_theme();
            $is_newest_sdk_plugin_active = ( $current_theme->stylesheet === $wpbay_sdk_newest_sdk->plugin_path );

            $current_theme_parent = $current_theme->parent();
            if ( ! $is_newest_sdk_plugin_active && $current_theme_parent instanceof WP_Theme ) {
                $is_newest_sdk_plugin_active = ( $wpbay_sdk_newest_sdk->plugin_path === $current_theme_parent->stylesheet );
            }
        }
        else
        {
            $is_newest_sdk_plugin_active = false;
        }
    }

    if ( $is_current_sdk_newest && ! $is_newest_sdk_plugin_active && !$wpbay_sdk_active_plugins->newest->in_activation ) {
        $wpbay_sdk_active_plugins->newest->in_activation = true;
        update_option( 'wpbay_sdk_active_plugins', $wpbay_sdk_active_plugins, false );
    }

    if ( ! $wpbay_sdk_is_theme ) {
        $sdk_starter_path = wpbay_sdk_normalize_path( WP_PLUGIN_DIR . '/' . $this_sdk_relative_path . '/WPBay_Loader.php' );
    } else {
        $sdk_starter_path = wpbay_sdk_normalize_path( $themes_directory . '/' . str_replace( "../{$themes_directory_name}/", '', $this_sdk_relative_path ) . '/WPBay_Loader.php' );
    }

    $is_newest_sdk_path_valid = ( $is_newest_sdk_plugin_active || $wpbay_sdk_active_plugins->newest->in_activation ) && file_exists( $sdk_starter_path );

    if ( ! $is_newest_sdk_path_valid && ! $is_current_sdk_newest ) {
        unset( $wpbay_sdk_active_plugins->plugins[ $wpbay_sdk_active_plugins->newest->sdk_path ] );
    }

    if ( ! ( $is_newest_sdk_plugin_active || $wpbay_sdk_active_plugins->newest->in_activation ) || ! $is_newest_sdk_path_valid || 
            ( $this_sdk_relative_path === $wpbay_sdk_active_plugins->newest->sdk_path && version_compare( $wpbay_sdk_active_plugins->newest->version, $wpbay_sdk_version, '>' ) ) ) 
    {
        wpbay_sdk_fallback_to_newest_active_sdk();
    } else {
        if ( $is_newest_sdk_plugin_active && $this_sdk_relative_path === $wpbay_sdk_active_plugins->newest->sdk_path && 
                ( $wpbay_sdk_active_plugins->newest->in_activation || ( class_exists( 'WPBaySDK\WPBay_SDK' ) && ( ! empty($wpbay_sdk_version) || version_compare( $wpbay_sdk_version, $wpbay_sdk_version, '<' ) ) ) ) ) {
            
            if ( $wpbay_sdk_active_plugins->newest->in_activation && ! $is_newest_sdk_type_theme ) {
                $wpbay_sdk_active_plugins->newest->in_activation = false;
                update_option( 'wpbay_sdk_active_plugins', $wpbay_sdk_active_plugins, false );
            }

            if( !defined('WP_FS__SDK_VERSION') )
            {
                if ( wpbay_sdk_newest_sdk_plugin_first() )
                {
                    if ( class_exists( 'WPBaySDK\WPBay_SDK' ) ) 
                    {
                        $last_redirect = get_transient('wpbay_sdk_redirect_timestamp');
                        $current_time = time();
                        if (isset($_SERVER['REQUEST_URI']) && (!$last_redirect || ($current_time - $last_redirect > 10))) 
                        {
                            set_transient('wpbay_sdk_redirect_timestamp', $current_time, 10);
                            wpbay_sdk_redirect( sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) );
                        }
                    }
                }
            }
        }
    }
}
if ( ! class_exists( 'WPBaySDK\WPBay_SDK_Loader' ) ) 
{
    class WPBay_SDK_Loader 
    {
        public static function load_sdk( $args ) 
        {
            $product_slug = __FILE__;
            global $wpbay_sdk_version;
            if(isset($args['product_file']))
            {
                $fallback_file = $args['product_file'];
            }
            else
            {
                $fallback_file = __FILE__;
            }
            $caller_file = wpbay_sdk_get_last_caller();
            $product_basename = wpbay_sdk_extract_basename($caller_file);
            if(empty($product_basename))
            {
                $product_basename = $fallback_file;
            }
            $product_slug = wpbay_sdk_extract_slug($caller_file);
            $sdk_var = 'wpbay_sdk_' . $product_basename;
            global $$sdk_var;

            $current_version = isset( $wpbay_sdk_version ) ? $wpbay_sdk_version : '0.0.0';

            if ( version_compare( $wpbay_sdk_version, $current_version, '>' ) ) 
            {
                $$sdk_var = null;
            }
            if ( ! isset( $$sdk_var ) ) 
            {
                $$sdk_var = WPBay_SDK::get_instance( $args, $product_slug, $wpbay_sdk_version );
            }
            return $$sdk_var;
        }
    }
    $wpbay_sdk_latest_loader = '\WPBaySDK\WPBay_SDK_Loader';
}
?>