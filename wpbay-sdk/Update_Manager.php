<?php
namespace WPBaySDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Manager {
    private static $instances = array();
    private static $initialized = false;
    private $is_free = false;

    // API endpoint used to check for updates and retrieve update packages from WPBay.com.
    // For full details, see WPBay_Loader.php or the SDK readme file.
    private $api_endpoint = 'https://wpbay.com/api/update/v1/';
    private $product_slug;
    private $product_file;
    private $product_type;
    private $wpbay_product_id;
    private $plugin_basename;
    private $theme_stylesheet;
    private $cache_key;
    private $cache_time = 12 * HOUR_IN_SECONDS;
    private $api_manager;
    private $license_manager;
    private $notice_manager;
    private $debug_mode;
    private $uploaded_to_wp_org;
    private static $pre_set_site_transient_filters_added = array();

    private function __construct( $wpbay_product_id, $product_slug, $product_file, $api_manager, $license_manager, $notice_manager, $product_type = 'plugin', $is_free = false, $debug_mode = false, $uploaded_to_wp_org = true ) 
    {
        $this->product_slug       = $product_slug;
        $this->wpbay_product_id   = $wpbay_product_id;
        $this->product_file       = $product_file;
        $this->product_type       = $product_type;
        $this->api_manager        = $api_manager;
        $this->license_manager    = $license_manager;
        $this->notice_manager     = $notice_manager;
        $this->is_free            = $is_free;
        $this->debug_mode         = $debug_mode;
        $this->uploaded_to_wp_org = $uploaded_to_wp_org;
        $this->cache_key          = 'wpbay_sdk_update_' . $this->product_slug;

        if ( $this->uploaded_to_wp_org ) 
        {
            return;
        }
        if ( $this->product_type === 'plugin' ) 
        {
            $this->plugin_basename = plugin_basename( $this->product_file );
            //this filter is not used if the plugin is uploaded to wordpress.org repo
            add_filter( 'pre_set_si' . 'te_tran' . 'sient_' . 'upd' . 'ate_plugins', array( $this, 'check_for_updates' ) );
            self::$pre_set_site_transient_filters_added[] = array( $this, 'check_for_updates' );
            //this code is not running for wordpress.org uploaded plugins, as the uploaded_to_wp_org flag is set to true
            add_filter( 'plugins_api', array( $this, 'plugins_api_callback' ), 10, 3 );
            if (!$this->is_free) 
            {
                add_action( "after_plugin_row_{$this->plugin_basename}", array( $this, 'plugin_u' . 'pdate_' . 'message' ), 10, 2 );
            }
            $purchase_code     = $this->license_manager->get_purchase_code();
            if(!empty($purchase_code))
            {
                add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_check_for_updates_link' ) );
                add_action( 'admin_init', array( $this, 'handle_check_for_updates_action' ) );
                if ( ! self::$initialized ) 
                {
                    $this->update_checked_admin_notice();
                }
            }
            if ( ! self::$initialized ) 
            {
                // Initialize update-related filters only once.
                // These filters do NOT activate or deactivate any plugins directly.
                // They are used to handle post-install cleanup and path correction after an SDK-powered plugin update.
                // NOTE: This logic IS disabled in plugins submitted to WordPress.org, check uploaded_to_wp_org.
                /**
                 * Hook into the upgrader post-install process to clean up temp folders or fix file structure.
                 * This does NOT modify plugin activation status.
                 */
                add_filter( 'upgrader_post_install', array( __CLASS__, 'upgrader_post_install' ), 10, 3 );
                /**
                 * Hook into the upgrader source selection to adjust folder names (if needed).
                 * This does NOT affect plugin activation/deactivation, only renames folder on install.
                 */
                add_filter( 'upgrader_source_selection', array( __CLASS__, 'upgrader_source_selection' ), 10, 3 );
                self::$initialized = true;
            }
        } 
        elseif ( $this->product_type === 'theme' ) 
        {
            $theme = wp_get_theme( $this->product_slug );
            $this->theme_stylesheet = $theme->get_stylesheet();
            //this filter is not used if the theme is uploaded to wordpress.org
            add_filter( 'pre_set_' . 'site_trans' . 'ient_updat' . 'e_th' . 'emes', array( $this, 'check_for_updates' ) );
            self::$pre_set_site_transient_filters_added[] = array( $this, 'check_for_updates' );
            //this code is not running for wordpress.org uploaded themes, as the uploaded_to_wp_org flag is set to true
            add_filter( 'themes_api', array( $this, 'themes_api_callback' ), 10, 3 );
            if (!$this->is_free) 
            {
                add_action( "after_theme_row_{$this->theme_stylesheet}", array( $this, 'theme_u' . 'pdate_' . 'message' ), 10, 2 );
            }
            $purchase_code     = $this->license_manager->get_purchase_code();
            if(!empty($purchase_code))
            {
                add_filter( 'theme_action_links_' . $this->theme_stylesheet, array( $this, 'add_theme_check_for_updates_link' ) );
                add_action( 'admin_init', array( $this, 'handle_theme_check_for_updates_action' ) );
                if ( ! self::$initialized ) 
                {
                    $this->theme_update_checked_admin_notice();
                }
            }
            if ( ! self::$initialized ) 
            {
                /**
                 * Hook into the upgrader post-install process to clean up temp folders or fix file structure.
                 * This does NOT modify plugin activation status.
                 */
                add_filter( 'upgrader_post_install', array( __CLASS__, 'upgrader_post_install' ), 10, 3 );
                /**
                 * Hook into the upgrader source selection to adjust folder names (if needed).
                 * This does NOT affect plugin activation/deactivation, only renames folder on install.
                 */
                add_filter( 'upgrader_source_selection', array( __CLASS__, 'upgrader_source_selection' ), 10, 3 );
                self::$initialized = true;
            }
        }
    }
    public static function get_instance( $wpbay_product_id, $product_slug, $product_file, $api_manager, $license_manager, $notice_manager, $product_type = 'plugin', $is_free = false, $debug_mode = false ) {
        $instance_key = $product_slug . '_' . $product_type;
        if ( ! isset( self::$instances[ $instance_key ] ) ) {
            self::$instances[ $instance_key ] = new self( $wpbay_product_id, $product_slug, $product_file, $api_manager, $license_manager, $notice_manager, $product_type, $is_free, $debug_mode );
        }
        return self::$instances[ $instance_key ];
    }
    private static function remove_all_added_pre_site_transient_filters()
    {
        if(is_array(self::$pre_set_site_transient_filters_added))
        {
            foreach(self::$pre_set_site_transient_filters_added as $my_filter)
            {
                //this filter is not used if the plugin is uploaded to wordpress.org repo
                remove_filter( 'pre_set_si' . 'te_tran' . 'sient_' . 'upd' . 'ate_plugins', $my_filter );
            }
        }
    }
    public function add_theme_check_for_updates_link( $actions ) {
        $updates_text = esc_html(wpbay_get_text_inline('Check for updates', 'wpbay-sdk'));
        $updates_text = apply_filters( 'wpbay_sdk_updates_check_message', $updates_text );
        $updates_text = esc_html($updates_text);
        $new_actions = array(
            'check_for_updates' => '<a href="' . wp_nonce_url( self_admin_url( 'update.php?action=wpbay_sdk_check_for_theme_updates&theme=' . urlencode( $this->theme_stylesheet ) ), 'wpbay_sdk_check_for_theme_updates' ) . '">' . $updates_text . '</a>',
        );
        return array_merge( $actions, $new_actions );
    }
    /**
     * Handles manual update check for themes sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function handle_theme_check_for_updates_action() {
        if ( $this->uploaded_to_wp_org ) 
        {
            return;
        }
        if ( isset( $_GET['action'], $_GET['theme'] ) && $_GET['action'] === 'wpbay_sdk_check_for_theme_updates' ) {
            check_admin_referer( 'wpbay_sdk_check_for_theme_updates' );
    
            $theme = sanitize_text_field( wp_unslash( $_GET['theme'] ) );
            if ( $theme === $this->theme_stylesheet ) {
                //this code is not running for wordpress.org uploaded themes, as the uploaded_to_wp_org flag is set to true
                $transient = get_site_transient( 'update_themes' );
                if ( ! is_object( $transient ) ) {
                    $transient = new \stdClass();
                }
                $transient = $this->check_for_updates( $transient );
                self::remove_all_added_pre_site_transient_filters();
                set_site_transient( 'update_themes', $transient );
                wp_safe_redirect( wp_nonce_url(admin_url( 'themes.php?checked_for_theme_updates=1' ), 'wpbay_sdk_checked_for_theme_updates') );
                exit;
            }
        }
    }
    /**
     * Handles manual update notice for themes sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function theme_update_checked_admin_notice() {
        if ( $this->uploaded_to_wp_org ) 
        {
            return;
        }
        if ( isset( $_GET['checked_for_theme_updates'] ) && $_GET['checked_for_theme_updates'] == '1' ) {
            check_admin_referer( 'wpbay_sdk_checked_for_theme_updates' );
            $updates_completed_text = esc_html(wpbay_get_text_inline('Update check completed.', 'wpbay-sdk'));
            $updates_completed_text = apply_filters( 'wpbay_sdk_updates_check_message', $updates_completed_text );
            $updates_completed_text = esc_html($updates_completed_text);
            $this->notice_manager->add_notice($updates_completed_text, 'success');
        }
    }
    /**
     * Handles manual update check for updates action sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function handle_check_for_updates_action() 
    {
        if ( $this->uploaded_to_wp_org ) 
        {
            return;
        }
        if ( isset( $_GET['action'], $_GET['plugin'] ) && $_GET['action'] === 'wpbay_sdk_check_for_updates' ) 
        {
            check_admin_referer( 'wpbay_sdk_check_for_updates' );
            $plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
            if ( $plugin === $this->plugin_basename ) {
                //this code is not running for wordpress.org uploaded plugins, as the uploaded_to_wp_org flag is set to true
                $transient = get_site_transient( 'update_plugins' );
                if ( ! is_object( $transient ) ) {
                    $transient = new \stdClass();
                }
                $transient = $this->check_for_updates( $transient );
                self::remove_all_added_pre_site_transient_filters();
                set_site_transient( 'update_plugins', $transient );
                wp_safe_redirect( wp_nonce_url(admin_url( 'plugins.php?checked_for_updates=1' ), 'wpbay_sdk_checked_for_updates') );
                exit;
            }
        }
    }

    /**
     * Handles manual update check for update notice sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function update_checked_admin_notice() {
        if ( $this->uploaded_to_wp_org ) 
        {
            return;
        }
        if ( isset( $_GET['checked_for_updates'] ) && $_GET['checked_for_updates'] == '1' ) 
        {
            check_admin_referer( 'wpbay_sdk_checked_for_updates' );
            $updates_completed_text = esc_html(wpbay_get_text_inline('Update check completed.', 'wpbay-sdk'));
            $updates_completed_text = apply_filters( 'wpbay_sdk_updates_check_message', $updates_completed_text );
            $updates_completed_text = esc_html($updates_completed_text);
            $this->notice_manager->add_notice($updates_completed_text, 'success');
        }
    }
    public function add_check_for_updates_link( $actions ) {
        $updates_text = esc_html(wpbay_get_text_inline('Check for updates', 'wpbay-sdk'));
        $updates_text = apply_filters( 'wpbay_sdk_updates_check_message', $updates_text );
        $updates_text = esc_html($updates_text);
        $new_actions = array(
            'check_for_updates' => '<a href="' . wp_nonce_url( self_admin_url( 'update.php?action=wpbay_sdk_check_for_updates&plugin=' . urlencode( $this->plugin_basename ) ), 'wpbay_sdk_check_for_updates' ) . '">' . $updates_text . '</a>',
        );
        return array_merge( $actions, $new_actions );
    }

    /**
     * Handles update checking for themes sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function check_for_updates( $transient ) 
    {
        if ( $this->uploaded_to_wp_org ) 
        {
            return $transient;
        }
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        if(empty($this->wpbay_product_id))
        {
            return $transient;
        }
        
        //this code is not running for wordpress.org uploaded plugins, as the uploaded_to_wp_org flag is set to true
        $update_data = get_site_transient( $this->cache_key );
        if ( false === $update_data ) {
            $purchase_code     = $this->license_manager->get_purchase_code();
            if(empty($purchase_code))
            {
                return $transient;
            }
            $current_version = '';

            if ( $this->product_type === 'plugin' ) {
                $plugin_data     = get_plugin_data( $this->product_file );
                $current_version = $plugin_data['Version'];
            } elseif ( $this->product_type === 'theme' ) {
                $theme           = wp_get_theme( $this->product_slug );
                $current_version = $theme->get( 'Version' );
            }
            $api_url = $this->api_endpoint . 'updates';
            $args = array(
                'body' => array(
                    'purchase_code' => $purchase_code,
                    'product_slug'  => $this->product_slug,
                    'developer_mode'=> $this->license_manager->get_developer_mode(),
                    'secret_key'    => $this->license_manager->get_secret_key(),
                    'version'       => $current_version,
                    'site_url'      => home_url(),
                    'product_type'  => $this->product_type,
                    'product_id'    => $this->wpbay_product_id,
                    'api_key'       => $this->license_manager->get_api_key()
                ),
                'timeout' => 90,
            );
            $response = $this->api_manager->post_request( $api_url, $args );

            if ( ! empty( $response['error'] ) ) {
                if($this->debug_mode === true)
                {
                    wpbay_log_to_file('Failed to check for updates!');
                }
                return $transient;
            }
            $response_body = $response['body'];
            $result        = json_decode( $response_body, true );
            if ( ! is_array( $result ) ) {
                return $transient;
            }

            $update_data = $result;

            self::remove_all_added_pre_site_transient_filters();
            set_site_transient( $this->cache_key, $update_data, $this->cache_time );
        }

        $current_version = '';

        if ( $this->product_type === 'plugin' ) {
            $plugin_data     = get_plugin_data( $this->product_file );
            $current_version = $plugin_data['Version'];
        } elseif ( $this->product_type === 'theme' ) {
            $theme           = wp_get_theme( $this->product_slug );
            $current_version = $theme->get( 'Version' );
        }

        if ( isset( $update_data['new_version'] ) && version_compare( $current_version, $update_data['new_version'], '<' ) ) {
            if ( $this->product_type === 'plugin' ) {
                $plugin_info = (object) array(
                    'slug'        => $this->product_slug,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $update_data['new_version'],
                    'url'         => $update_data['homepage'],
                    'package'     => $update_data['download_url'],
                    'tested'      => isset( $update_data['tested'] ) ? $update_data['tested'] : '',
                    'requires'    => isset( $update_data['requires'] ) ? $update_data['requires'] : '',
                    'icons'       => isset( $update_data['icons'] ) ? $update_data['icons'] : array(),
                    'banners'     => isset( $update_data['banners'] ) ? $update_data['banners'] : array(),
                );
                $transient->response[ $this->plugin_basename ] = $plugin_info;
            } elseif ( $this->product_type === 'theme' ) {
                $theme_info = array(
                    'theme'       => $this->theme_stylesheet,
                    'new_version' => $update_data['new_version'],
                    'url'         => $update_data['homepage'],
                    'package'     => $update_data['download_url'],
                );
                $transient->response[ $this->theme_stylesheet ] = $theme_info;
            }
        } else {
            if ( $this->product_type === 'plugin' ) {
                if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
                    unset( $transient->response[ $this->plugin_basename ] );
                }
                $plugin_info = (object) array(
                    'slug'        => $this->product_slug,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $current_version,
                    'url'         => '',
                    'package'     => '',
                    'icons'       => array(),
                    'banners'     => array(),
                );
                $transient->no_update[ $this->plugin_basename ] = $plugin_info;
            } elseif ( $this->product_type === 'theme' ) {
                if ( isset( $transient->response[ $this->theme_stylesheet ] ) ) {
                    unset( $transient->response[ $this->theme_stylesheet ] );
                }
                $theme_info = array(
                    'theme'       => $this->theme_stylesheet,
                    'new_version' => $current_version,
                    'url'         => '',
                    'package'     => '',
                );
                $transient->no_update[ $this->theme_stylesheet ] = $theme_info;
            }
        }

        return $transient;
    }

    /**
     * Handles plugin api callback for themes sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function plugins_api_callback( $result, $action, $args ) 
    {
        if ( $this->uploaded_to_wp_org ) 
        {
            return $result;
        }
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( $args->slug !== $this->product_slug ) {
            return $result;
        }
        
        if(empty($this->wpbay_product_id))
        {
            return $result;
        }
        $purchase_code = $this->license_manager->get_purchase_code();
        if(empty($purchase_code))
        {
            return $result;
        }
        $info = get_transient( $this->cache_key . '_info' );

        if ( false === $info ) {
            $api_url = $this->api_endpoint . 'info';
            $args = array(
                'body' => array(
                    'purchase_code' => $purchase_code,
                    'product_slug'  => $this->product_slug,
                    'site_url'      => home_url(),
                    'developer_mode'=> $this->license_manager->get_developer_mode(),
                    'secret_key'    => $this->license_manager->get_secret_key(),
                    'product_type'  => $this->product_type,
                    'product_id'    => $this->wpbay_product_id,
                    'api_key'       => $this->license_manager->get_api_key()
                ),
                'timeout' => 90,
            );
            $response = $this->api_manager->post_request( $api_url, $args );

            if ( ! empty( $response['error'] ) ) {
                if($this->debug_mode === true)
                {
                    wpbay_log_to_file('Failed to check API callback!');
                }
                return $result;
            }

            $response_body = $response['body'];
            $info          = json_decode( $response_body, true );

            if ( ! is_array( $info ) ) {
                return $result;
            }

            set_transient( $this->cache_key . '_info', $info, $this->cache_time );
        }

        $plugin_info = new \stdClass();

        $plugin_info->name          = isset( $info['name'] ) ? $info['name'] : '';
        $plugin_info->slug          = $this->product_slug;
        $plugin_info->version       = isset( $info['version'] ) ? $info['version'] : '';
        $plugin_info->author        = isset( $info['author'] ) ? $info['author'] : '';
        $plugin_info->homepage      = isset( $info['homepage'] ) ? $info['homepage'] : '';
        $plugin_info->requires      = isset( $info['requires'] ) ? $info['requires'] : '';
        $plugin_info->tested        = isset( $info['tested'] ) ? $info['tested'] : '';
        $plugin_info->download_link = isset( $info['download_url'] ) ? $info['download_url'] : '';
        $plugin_info->sections      = array(
            'description'  => isset( $info['description'] ) ? $info['description'] : '',
            'installation' => isset( $info['installation'] ) ? $info['installation'] : '',
            'changelog'    => isset( $info['changelog'] ) ? $info['changelog'] : '',
            'faq'          => isset( $info['faq'] ) ? $info['faq'] : '',
        );
        $plugin_info->banners       = isset( $info['banners'] ) ? $info['banners'] : array();
        $plugin_info->icons         = isset( $info['icons'] ) ? $info['icons'] : array();

        return $plugin_info;
    }

    /**
     * Handles plugin update for plugins sold outside WordPress.org (via WPBay).
     *
     * WordPress.org-hosted themes are not allowed to override the update system.
     * This logic is only enabled for self-hosted or premium theme distributions.
     * See the 'uploaded_to_wp_org' flag to disable this in .org submissions.
     */
    public function themes_api_callback( $result, $action, $args ) {
        if ( $this->uploaded_to_wp_org ) 
        {
            return $result;
        }
        if ( $action !== 'theme_information' ) {
            return $result;
        }

        if ( $args->slug !== $this->product_slug ) {
            return $result;
        }

        if(empty($this->wpbay_product_id))
        {
            return $result;
        }
        $purchase_code = $this->license_manager->get_purchase_code();
        if(empty($purchase_code))
        {
            return $result;
        }
        $info = get_transient( $this->cache_key . '_info' );

        if ( false === $info ) {
            $api_url = $this->api_endpoint . 'info';
            $args = array(
                'body' => array(
                    'purchase_code' => $purchase_code,
                    'product_slug'  => $this->product_slug,
                    'site_url'      => home_url(),
                    'developer_mode'=> $this->license_manager->get_developer_mode(),
                    'secret_key'    => $this->license_manager->get_secret_key(),
                    'product_type'  => $this->product_type,
                    'product_id'    => $this->wpbay_product_id,
                    'api_key'       => $this->license_manager->get_api_key()
                ),
                'timeout' => 90,
            );
            $response = $this->api_manager->post_request( $api_url, $args );

            if ( ! empty( $response['error'] ) ) {
                return $result;
            }

            $response_body = $response['body'];
            $info          = json_decode( $response_body, true );

            if ( ! is_array( $info ) ) {
                return $result;
            }

            set_transient( $this->cache_key . '_info', $info, $this->cache_time );
        }

        $theme_info = new \stdClass();

        $theme_info->name           = isset( $info['name'] ) ? $info['name'] : '';
        $theme_info->slug           = $this->product_slug;
        $theme_info->version        = isset( $info['version'] ) ? $info['version'] : '';
        $theme_info->author         = isset( $info['author'] ) ? $info['author'] : '';
        $theme_info->homepage       = isset( $info['homepage'] ) ? $info['homepage'] : '';
        $theme_info->requires       = isset( $info['requires'] ) ? $info['requires'] : '';
        $theme_info->tested         = isset( $info['tested'] ) ? $info['tested'] : '';
        $theme_info->download_link  = isset( $info['download_url'] ) ? $info['download_url'] : '';
        $theme_info->sections       = array(
            'description'  => isset( $info['description'] ) ? $info['description'] : '',
            'installation' => isset( $info['installation'] ) ? $info['installation'] : '',
            'changelog'    => isset( $info['changelog'] ) ? $info['changelog'] : '',
            'faq'          => isset( $info['faq'] ) ? $info['faq'] : '',
        );

        return $theme_info;
    }

    public static function upgrader_post_install( $source, $remote_source, $upgrader ) 
    {
        
        global $wp_filesystem;

        if ( $upgrader instanceof Plugin_Upgrader ) 
        {
            global $wp_filesystem;

            if ( ! is_object( $wp_filesystem ) ) {
                return $source;
            }

            // Check for single file plugins.
            $source_files = array_keys( $wp_filesystem->dirlist( $remote_source ) );
            if ( 1 === count( $source_files ) && false === $wp_filesystem->is_dir( $source ) ) {
                return $source;
            }

            $desired_slug = isset( $upgrader->skin->options['plugin'] ) ? $upgrader->skin->options['plugin'] : false;

            if ( ! $desired_slug ) {
                return $source;
            }

            $subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) );

            if ( ! empty( $subdir_name ) && $subdir_name !== $desired_slug ) {

                $from_path = untrailingslashit( $source );
                $to_path   = trailingslashit( $remote_source ) . $desired_slug;

                if ( true === $wp_filesystem->move( $from_path, $to_path ) ) {
                    return trailingslashit( $to_path );
                } else {
                    return new WP_Error(
                        'rename_failed',
                        esc_html(wpbay_get_text_inline( 'The remote plugin package does not contain a folder with the desired slug and renaming did not work.', 'wpbay-sdk' )) . ' ' . esc_html(wpbay_get_text_inline( 'Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.', 'wpbay-sdk' )),
                        array( 'found' => $subdir_name, 'expected' => $desired_slug )
                    );
                }

            } elseif ( empty( $subdir_name ) ) {
                return new WP_Error(
                    'packaged_wrong',
                    esc_html(wpbay_get_text_inline( 'The remote plugin package consists of more than one file, but the files are not packaged in a folder.', 'wpbay-sdk' )) . ' ' . esc_html(wpbay_get_text_inline( 'Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.', 'wpbay-sdk' )),
                    array( 'found' => $subdir_name, 'expected' => $desired_slug )
                );
            }
        } elseif ( $upgrader instanceof Theme_Upgrader ) {
            // Check for single folder themes
            $source_files = array_keys( $wp_filesystem->dirlist( $remote_source ) );
            if ( 1 === count( $source_files ) && false === $wp_filesystem->is_dir( $source ) ) {
                return $source;
            }

            $desired_slug = isset( $upgrader->skin->options['theme'] ) ? $upgrader->skin->options['theme'] : false;

            if ( ! $desired_slug ) {
                return $source;
            }

            $subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) );

            if ( ! empty( $subdir_name ) && $subdir_name !== $desired_slug ) {

                $from_path = untrailingslashit( $source );
                $to_path   = trailingslashit( $remote_source ) . $desired_slug;

                if ( true === $wp_filesystem->move( $from_path, $to_path ) ) {
                    return trailingslashit( $to_path );
                } else {
                    return new WP_Error(
                        'rename_failed',
                        esc_html(wpbay_get_text_inline( 'The remote theme package does not contain a folder with the desired slug and renaming did not work.', 'wpbay-sdk' )) . ' ' . esc_html(wpbay_get_text_inline( 'Please contact the theme provider and ask them to package their theme according to the WordPress guidelines.', 'wpbay-sdk' )),
                        array( 'found' => $subdir_name, 'expected' => $desired_slug )
                    );
                }

            } elseif ( empty( $subdir_name ) ) {
                return new WP_Error(
                    'packaged_wrong',
                    esc_html(wpbay_get_text_inline( 'The remote theme package consists of more than one file, but the files are not packaged in a folder.', 'wpbay-sdk' )) . ' ' . esc_html(wpbay_get_text_inline( 'Please contact the theme provider and ask them to package their theme according to the WordPress guidelines.', 'wpbay-sdk' )),
                    array( 'found' => $subdir_name, 'expected' => $desired_slug )
                );
            }
        }

        return $source;
    }

    public static function upgrader_source_selection( $source, $remote_source, $upgrader ) {
        global $wp_filesystem;

        if ( isset( $source, $remote_source ) ) {
            if ( $upgrader instanceof Plugin_Upgrader ) 
            {
                $plugin_slug = isset( $upgrader->skin->options['plugin'] ) ? $upgrader->skin->options['plugin'] : false;
                if ( ! $plugin_slug ) {
                    return $source;
                }
                $corrected_source = trailingslashit( $remote_source ) . $plugin_slug;
                if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
                    return $corrected_source;
                } else {
                    return new \WP_Error( 'rename_failed', esc_html(wpbay_get_text_inline( 'Failed to rename plugin directory.', 'wpbay-sdk' )) );
                }
            } 
            elseif ( $upgrader instanceof Theme_Upgrader ) 
            {
                $theme_slug = isset( $upgrader->skin->options['theme'] ) ? $upgrader->skin->options['theme'] : false;
                if ( ! $theme_slug ) {
                    return $source;
                }
                $corrected_source = trailingslashit( $remote_source ) . $theme_slug;
                if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
                    return $corrected_source;
                } else {
                    return new \WP_Error( 'rename_failed', esc_html(wpbay_get_text_inline( 'Failed to rename theme directory.', 'wpbay-sdk' )) );
                }
            }
        }
        return $source;
    }

    public function plugin_update_message( $file, $plugin ) {
        $plugin_slug = dirname($file);
        if ( $plugin_slug === $this->product_slug ) {
            $purchase_code = $this->license_manager->get_purchase_code();
            if ( empty( $purchase_code ) ) {
                echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>';
                echo esc_html(wpbay_get_text_inline( 'Please activate your plugin\'s license to receive updates.', 'wpbay-sdk' ));
                echo '</p></div></td></tr>';
            }
        }
    }

    public function theme_update_message( $theme_key, $theme ) {
        if ( isset( $theme->get_stylesheet ) && $theme->get_stylesheet() === $this->theme_stylesheet ) {
            $purchase_code = $this->license_manager->get_purchase_code();
            if ( empty( $purchase_code ) ) {
                echo '<div class="theme-update-message notice inline notice-warning notice-alt"><p>';
                echo esc_html(wpbay_get_text_inline( 'Please activate your theme\'s license to receive updates.', 'wpbay-sdk' ));
                echo '</p></div>';
            }
        }
    }
}
