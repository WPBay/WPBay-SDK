<?php
/**
 * @package     WPBay
 * @copyright   Copyright (c) 2024, WPBay
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.0.0
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
        'site-editor.php' === basename( $_SERVER['SCRIPT_FILENAME'] ) ||
        (
            function_exists( 'wp_is_json_request' ) &&
            wp_is_json_request() &&
            ! empty( $_GET['wp_theme_preview'] )
        )
    )
) 
{
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
                    if (!$last_redirect || ($current_time - $last_redirect > 10)) 
                    {
                        set_transient('wpbay_sdk_redirect_timestamp', $current_time, 10);
                        wpbay_sdk_redirect( $_SERVER['REQUEST_URI'] );
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
                        if (!$last_redirect || ($current_time - $last_redirect > 10)) 
                        {
                            set_transient('wpbay_sdk_redirect_timestamp', $current_time, 10);
                            wpbay_sdk_redirect( $_SERVER['REQUEST_URI'] );
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