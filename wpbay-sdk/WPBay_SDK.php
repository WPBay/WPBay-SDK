<?php
namespace WPBaySDK;

if (! defined('ABSPATH')) 
{
    exit;
}

if ( ! class_exists( 'WPBaySDK\WPBay_SDK' ) ) 
{
    global $wpbay_text_overrides;
    $wpbay_text_overrides = array();
    class WPBay_SDK 
    {
        protected $_slug = 'wpbay-sdk';
        private static $instances = array();
        private static $registered_hooks = array();
        private static $admin_menu_added = false;
        private static $network_admin_menu_added = false;

        private $disable_feedback = false;
        private $disable_contact_form = false;
        private $disable_upgrade_form = false;
        private $disable_analytics = false;
        private $rating_notice = false;
        private $no_activation_required = false;
        
        private $product_slug = __FILE__;
        private $product_name = '';
        private $product_basename = __FILE__;
        private $this_sdk_version = '0.0.0';

        private $analytics_manager;
        private $license_manager;
        private $notice_manager;
        private $api_manager;
        private $menu_manager = null;
        private $update_manager = null;
        private $purchase_manager = null;
        private $contact_form_manager = null;
        private $feedback_manager = null;
        private $debug_mode = false;

        private $api_key = '';
        private $developer_mode = '0';
        private $secret_key = '';
        private $wpbay_product_id = false;
        private $product_file = __FILE__;
        private $activation_redirect = false;
        private $product_type = 'plugin';
        // Base URL for WPBay. For details, see WPBay_Loader.php or the SDK readme file.
        private $wpbay_sdk_url = 'https://wpbay.com/';
        // License API endpoint used for communicating with WPBay.com.
        // See WPBay_Loader.php or the SDK readme file for full data disclosure.
        private $api_endpoint = 'https://wpbay.com/api/purchase/v1/';

        private $is_free = false;
        private $is_upgradable = false;
        private $uploaded_to_wp_org = false;

        private function __construct($args, $product_slug, $this_sdk_version) 
        {
            $this->product_slug = $product_slug;
            $this->this_sdk_version = $this_sdk_version;
            if(isset($args['api_key']) && is_string($args['api_key']))
            {
                $this->api_key = $args['api_key'];
            }
            $call_context = wpbay_sdk_detect_context();
            if($call_context == 'theme')
            {
                $this->product_type = 'theme';
            }
            elseif($call_context == 'plugin')
            {
                $this->product_type = 'plugin';
            }
            else
            {
                $normalized_file = wpbay_sdk_normalize_path( __FILE__ );
                $theme_root      = wpbay_sdk_normalize_path( get_theme_root() );
                $plugin_root     = wpbay_sdk_normalize_path( dirname( plugin_dir_path( __FILE__ ) ) );

                if ( strpos( $normalized_file, $theme_root ) !== false ) {
                    $this->product_type = 'theme';
                } elseif ( strpos( $normalized_file, $plugin_root ) !== false ) {
                    $this->product_type = 'plugin';
                } else {
                    $this->product_type = 'unknown';
                }
            }
            if(isset($args['product_file']))
            {
                $this->product_file = wpbay_sdk_normalize_path($args['product_file']);
            }
            else
            {
                $this->product_file = wpbay_sdk_normalize_path($this->product_file);
            }
            if($call_context == 'theme')
            {
                $this->product_basename = $this->get_theme_basename();
            }
            elseif($call_context == 'plugin')
            {
                $this->product_basename = plugin_basename($this->product_file);
            }
            else
            {
                $this->product_basename = plugin_basename($this->product_file);
            }
            if($this->product_type == 'theme')
            {
                $theme = wp_get_theme($this->product_slug);
                $this->product_name = $theme->exists() ? $theme->get('Name') : $this->product_slug;
            }
            elseif($this->product_type == 'plugin')
            {
                $plugin_data = get_plugin_data($this->product_file, false, false);
                $this->product_name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $this->product_slug;
            }
            else
            {
                $this->product_name = $this->product_slug;
            }
            if(isset($args['is_free']) && is_bool($args['is_free']))
            {
                $this->is_free = $args['is_free'];
            }
            if(isset($args['debug_mode']) && is_bool($args['debug_mode']))
            {
                $this->debug_mode = $args['debug_mode'];
            }
            if(isset($args['no_activation_required']) && is_bool($args['no_activation_required']))
            {
                $this->no_activation_required = $args['no_activation_required'];
            }
            if(isset($args['is_upgradable']) && is_bool($args['is_upgradable']))
            {
                $this->is_upgradable = $args['is_upgradable'];
            }
            if(isset($args['uploaded_to_wp_org']) && is_bool($args['uploaded_to_wp_org']))
            {
                $this->uploaded_to_wp_org = $args['uploaded_to_wp_org'];
            }
            if(isset($args['wpbay_product_id']) && is_numeric($args['wpbay_product_id']))
            {
                $this->wpbay_product_id = $args['wpbay_product_id'];
            }
            if(isset($args['activation_redirect']) && is_string($args['activation_redirect']))
            {
                $this->activation_redirect = $args['activation_redirect'];
            }

            if(isset($args['disable_feedback']) && is_bool($args['disable_feedback'])) {
                $this->disable_feedback = $args['disable_feedback'];
            }
            
            if(isset($args['disable_contact_form']) && is_bool($args['disable_contact_form'])) {
                $this->disable_contact_form = $args['disable_contact_form'];
            }
            if(isset($args['disable_upgrade_form']) && is_bool($args['disable_upgrade_form'])) {
                $this->disable_upgrade_form = $args['disable_upgrade_form'];
            }
            
            if(isset($args['disable_analytics']) && is_bool($args['disable_analytics'])) {
                $this->disable_analytics = $args['disable_analytics'];
            }
            if(isset($args['rating_notice']) && !empty($args['rating_notice'])) {
                $this->rating_notice = $args['rating_notice'];
            }
            $this->initialize($args);
        }
        public function get_text_inline( $text, $key = '' ) {
            return wpbay_get_text_inline( $text, $this->_slug, $key );
        }

        public function esc_html_get_text_inline( $text, $key = '' ) {
            return wpbay_esc_html_get_text_inline( $text, $this->_slug, $key );
        }

        public function override_i18n( $key_value ) {
            wpbay_override_i18n( $key_value, $this->_slug );
        }
        public static function get_instance($args, $product_slug, $this_sdk_version) 
        {
            if (!isset(self::$instances[ $product_slug ])) 
            {
                self::$instances[ $product_slug ] = new self($args, $product_slug, $this_sdk_version);
            }
            return self::$instances[ $product_slug ];
        }
        public function get_contact_form_manager() 
        {
            return $this->contact_form_manager;
        }
        public function get_upgrade_form_manager() 
        {
            return $this->purchase_manager;
        }
        private function get_theme_basename() {
            $theme_root = get_theme_root();
            $relative_path = str_replace(trailingslashit($theme_root), '', $this->product_file);
            return $relative_path; 
        }
        public function enqueue_scripts( ) 
        {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-dialog' );
            wp_enqueue_style( 'wp-jquery-ui-dialog' );
        }
        private function initialize($args) 
        {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            $this->define_constants();
            $this->define_globals();
            $this->includes();
            $this->check_developer_mode();
            $this->notice_manager = Admin_Notice_Manager::get_instance($this->product_slug, $this->debug_mode);
            $this->api_manager = API_Manager::get_instance($this->api_endpoint, array(
                'cache_time'         => 3600, // cache time in seconds
                'rate_limit'         => 60, // rate limit: max requests per period
                'retry_count'        => 3, // number of times to retry on failure
                'retry_delay'        => 2, // delay between retries in seconds
                'rate_limit_period'  => 60 // rate limit period in seconds
            ), $this->debug_mode);
            $this->license_manager = License_Manager::get_instance(
                $this->product_slug,
                $this->product_name,
                $this->api_key,
                $this->developer_mode,
                $this->secret_key,
                $this->notice_manager,
                $this->wpbay_product_id,
                $this->this_sdk_version,
                $this->api_manager, 
                $this->debug_mode
            );
            // Initialize the custom update manager only if the product is not distributed via WordPress.org.
            // WordPress.org-hosted plugins must use WordPress's native update system and are not allowed to override it.
            // This check ensures compliance with WordPress.org guidelines by disabling external update checks when hosted on .org.
            if (!$this->uploaded_to_wp_org) 
            {
                $this->update_manager    = Update_Manager::get_instance( $this->wpbay_product_id, $this->product_slug, $this->product_file, $this->api_manager, $this->license_manager, $this->notice_manager, $this->product_type, $this->is_free, $this->debug_mode, $this->uploaded_to_wp_org );
            }
            if (!$this->disable_feedback && !empty($this->wpbay_product_id) && !empty($this->api_key)) 
            {
                $this->feedback_manager  = Feedback_Manager::get_instance($this->product_slug, $this->api_manager, $this->license_manager, $this->product_file, $this->wpbay_product_id, $this->debug_mode);
            }
            if (!$this->disable_analytics && !empty($this->wpbay_product_id) && !empty($this->api_key)) 
            {
                $this->analytics_manager = Analytics_Manager::get_instance($this->product_slug, $this->api_manager, $this->license_manager, $this->notice_manager, $this->wpbay_product_id, $this->product_type, $this->debug_mode);
            }
            if ($this->is_free && $this->is_upgradable && !empty($this->wpbay_product_id) && !empty($this->api_key)) 
            {
                $this->purchase_manager  = Purchase_Manager::get_instance(
                    $this->product_slug,
                    $this->wpbay_product_id,
                    $this->api_key,
                    $this->api_manager, 
                    $this->license_manager, 
                    $this->debug_mode
                );
            }
            $this->contact_form_manager = Contact_Form_Manager::get_instance($this->product_slug, $this->api_manager, $this->license_manager, $this->is_free, $this->no_activation_required, $this->wpbay_product_id, $this->debug_mode);
            $this->menu_manager = Menu_Manager::get_instance($args, $this->product_slug, $this->product_basename, $this->product_type, $this->uploaded_to_wp_org, $this->no_activation_required, $this->license_manager, $this->contact_form_manager, $this->purchase_manager, $this->wpbay_product_id, $this->api_key, $this->debug_mode);
            
            if(!empty($this->rating_notice) && !empty($this->wpbay_product_id) && !empty($this->api_key))
            {
                $activation_time = $this->license_manager->get_activation_time();
                if(is_numeric($activation_time))
                {
                    $target_timestamp = strtotime($this->rating_notice, $activation_time);
                    if($target_timestamp < time() && $this->license_manager->get_rating_shown() === false)
                    {
                        $this->license_manager->set_rating_shown($this->product_slug);
                        $notice_content = sprintf( wp_kses( 
                            // translators: %1$s: Product slug, %2$s: Product type, %3$s: Review URL
                            wpbay_get_text_inline( 'Hey, I noticed you are using the %1$s %2$s for some time now - that\'s really awesome! Could you please do a favor and give it <b><a href=\'%3$s\' target=\'_blank\'>a 5-star rating</a></b>? Just to help us spread the word and boost our motivation. Thank you!', 'wpbay-sdk'), 
                            array(  'b' => array(), 'a' => array( 'href' => array(), 'target' => array() ) ) ), $this->product_slug, $this->product_type, esc_url_raw($this->wpbay_sdk_url . '?post_type=product&p=' . $this->wpbay_product_id . '#reviews') );
                        $notice_content = apply_filters( 'wpbay_sdk_updates_check_message', $notice_content );
                        $this->notice_manager->add_notice($notice_content, 'success');
                    }
                }
            }
            $this->init_hooks();
            if ($this->is_developer_mode() === true || $this->is_debug_mode() === true) 
            {
                $modes = array();
                if ($this->is_developer_mode() === true) {
                    $modes[] = esc_html(wpbay_get_text_inline('Developer Mode', 'wpbay-sdk'));
                }
                if ($this->is_debug_mode() === true) {
                    $modes[] = esc_html(wpbay_get_text_inline('Debug Mode', 'wpbay-sdk'));
                }
                $modes_list = implode(' & ', $modes);
                $notice_content = sprintf(
                    // translators: %1$s: Product slug, %2$s: Mode (e.g., debug, test, etc.)
                    esc_html(wpbay_get_text_inline('WPBay SDK (%1$s) is currently running in %2$s. Don\'t forget to disable this before you go live!', 'wpbay-sdk')),
                    $this->product_slug,
                    $modes_list
                );
                $notice_content = apply_filters('wpbay_sdk_debug_developer_message', $notice_content, $modes);
                $this->notice_manager->add_notice($notice_content, 'warning');
            }
        }

        //helpful functions below
        function is_localhost_by_address() 
        {
            $url = home_url(isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '');
            if ( false !== strpos( $url, '127.0.0.1' ) || false !== strpos( $url, 'localhost' ) ) {
                return true;
            }
            if ( ! wpbay_sdk_startsWith( $url, 'http' ) ) {
                $url = 'http://' . $url;
            }
            $url_parts = wp_parse_url( $url );
            $subdomain = $url_parts['host'];
            return (
                wpbay_sdk_startsWith( $subdomain, 'local.' ) ||
                wpbay_sdk_startsWith( $subdomain, 'dev.' ) ||
                wpbay_sdk_startsWith( $subdomain, 'test.' ) ||
                wpbay_sdk_startsWith( $subdomain, 'stage.' ) ||
                wpbay_sdk_startsWith( $subdomain, 'staging.' ) ||
                wpbay_sdk_endsWith( $subdomain, '.dev' ) ||
                wpbay_sdk_endsWith( $subdomain, '.test' ) ||
                wpbay_sdk_endsWith( $subdomain, '.staging' ) ||
                wpbay_sdk_endsWith( $subdomain, '.local' ) ||
                wpbay_sdk_endsWith( $subdomain, '.example' ) ||
                wpbay_sdk_endsWith( $subdomain, '.invalid' ) ||
                wpbay_sdk_endsWith( $subdomain, '.myftpupload.com' ) ||
                wpbay_sdk_endsWith( $subdomain, '.ngrok.io' ) ||
                wpbay_sdk_endsWith( $subdomain, '.wpsandbox.pro' ) ||
                wpbay_sdk_startsWith( $subdomain, 'staging' ) ||
                wpbay_sdk_endsWith( $subdomain, '.staging.wpengine.com' ) ||
                wpbay_sdk_endsWith( $subdomain, '.dev.wpengine.com' ) ||
                wpbay_sdk_endsWith( $subdomain, '.wpengine.com' ) ||
                wpbay_sdk_endsWith( $subdomain, '.wpenginepowered.com' ) ||
                ( wpbay_sdk_endsWith( $subdomain, 'pantheonsite.io' ) && ( wpbay_sdk_startsWith( $subdomain, 'test-' ) || wpbay_sdk_startsWith( $subdomain, 'dev-' ) ) ) ||
                wpbay_sdk_endsWith( $subdomain, '.cloudwaysapps.com' ) ||
                ( ( wpbay_sdk_startsWith( $subdomain, 'staging-' ) || wpbay_sdk_startsWith( $subdomain, 'env-' ) ) && ( wpbay_sdk_endsWith( $subdomain, '.kinsta.com' ) || wpbay_sdk_endsWith( $subdomain, '.kinsta.cloud' ) ) ) ||
                wpbay_sdk_endsWith( $subdomain, '.dev.cc' ) ||
                wpbay_sdk_endsWith( $subdomain, '.mystagingwebsite.com' ) ||
                ( wpbay_sdk_endsWith( $subdomain, '.tempurl.host' ) || wpbay_sdk_endsWith( $subdomain, '.wpmudev.host' ) ) ||
                ( wpbay_sdk_endsWith( $subdomain, '.websitepro-staging.com' ) || wpbay_sdk_endsWith( $subdomain, '.websitepro.hosting' ) ) ||
                wpbay_sdk_endsWith( $subdomain, '.instawp.xyz' ) ||
                ( wpbay_sdk_endsWith( $subdomain, '-dev.10web.site' ) || wpbay_sdk_endsWith( $subdomain, '-dev.10web.cloud' ) )
            );
        }
        
        public function is_paid_or_trial_user() 
        {
            if($this->is_paying_user() || $this->is_trial_user())
            {
                return true;
            }
            return false;
        }
        public function is_not_paying_user() 
        {
            if($this->is_paying_user())
            {
                return false;
            }
            return true;
        }
        public function is_trial_user() 
        {
            if(!empty($this->license_manager->get_purchase_code()) && $this->license_manager->is_plan('trial'))
            {
                return true;
            }
            return false;
        }
        public function is_paying_user() 
        {
            if(!empty($this->license_manager->get_purchase_code()) && !$this->license_manager->is_plan('trial'))
            {
                return true;
            }
            return false;
        }
        public function is_plan($plan_name) 
        {
            if($this->license_manager->is_plan($plan_name))
            {
                return true;
            }
            return false;
        }
        //end of helpful functions
        
        public function get_first_time_path() 
        {
            if ( empty ( $this->activation_redirect ) ) 
            {
                return $this->activation_redirect;
            }
            $is_network = false;
            if(is_network_admin() && is_multisite())
            {
                $is_network = true;
            }
            if ( $is_network ) 
            {
                return network_admin_url( $this->activation_redirect );
            } 
            else 
            {
                return admin_url( $this->activation_redirect );
            }
        }
        public function is_theme() 
        {
            if($this->product_type === 'theme')
            {
                return true;
            }
            return false;
        }
        public function is_plugin() 
        {
            if($this->product_type === 'plugin')
            {
                return true;
            }
            return false;
        }
        public function get_api_key() 
        {
            return $this->api_key;
        }
        public function activate_product_event_hook()
        {
            if (!$this->is_user_admin()) 
            {
                return;
            }
            set_transient( 'wpbay_sdk_'  . $this->product_type . '_' . $this->product_slug . '_activated', true, 60 );
        }
        public function admin_init() 
        {
            if (!$this->is_free) 
            {
                $this->license_manager->admin_init();
            }
            if ( $this->is_product_activation() ) 
            {
                delete_transient( 'wpbay_sdk_'  . $this->product_type . '_' . $this->product_slug . '_activated' );
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Only checking existence of $_GET key, no trust or action taken
                if (! isset($_GET['activate-multi']))
                {
                    if(!empty($this->activation_redirect) && is_string($this->activation_redirect))
                    {
                        $redir_now = $this->get_first_time_path();
                        if(!empty($redir_now))
                        {
                            wpbay_sdk_redirect($redir_now, true);
                            return;
                        }
                    }
                }
            }
        }
        public function admin_menu() 
        {
            if (self::$admin_menu_added || $this->is_developer_mode() === false) 
            {
                return;
            }
            add_management_page(
                esc_html(wpbay_get_text_inline('WPBay Developer Mode', 'wpbay-sdk')),
                esc_html(wpbay_get_text_inline('WPBay Developer Mode', 'wpbay-sdk')),
                'manage_options',
                'wpbay-settings',
                array($this, 'settings_page')
            );
            self::$admin_menu_added = true;
        }
        public function settings_page() 
        {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(wpbay_get_text_inline('WPBay Settings', 'wpbay-sdk')); ?></h1>
                <?php settings_errors(); ?>
                <form method="post" action="options.php">
                    <?php
                    do_settings_sections('wpbay-settings');
                    ?>
                </form>
            </div>
            <?php
        }
        public function dismiss_notice() 
        {
            if(!current_user_can( 'edit_posts' ))
            {
                return;
            }
            check_ajax_referer('wpbay_sdk_dismiss_notice', 'nonce');
            $notice = isset($_POST['notice']) ? sanitize_text_field(wp_unslash($_POST['notice'])) : '';
            if ($notice) {
                update_option('wpbay_sdk_dismissed_notice_' . $notice, true, false);
            }
            wp_send_json_success();
        }
        public function network_admin_menu() 
        {
            if (self::$network_admin_menu_added || $this->is_developer_mode() === false) 
            {
                return;
            }
            wpbay_sdk_add_page_menu(
                esc_html(wpbay_get_text_inline('WPBay Network Developer Mode', 'wpbay-sdk')),
                esc_html(wpbay_get_text_inline('WPBay Network Developer Mode', 'wpbay-sdk')),
                'manage_network_options',
                'wpbay-network-settings',
                array($this, 'network_settings_page')
            );
            self::$admin_menu_added = true;
        }
        public function network_settings_page() 
        {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(wpbay_get_text_inline('WPBay Network Settings', 'wpbay-sdk')); ?></h1>
                <form method="post" action="edit.php?action=wpbay_sdk_network_settings">
                    <?php
                    do_settings_sections('wpbay-network-settings');
                    ?>
                </form>
            </div>
            <?php
        }
        private function check_developer_mode() 
        {
            $dev_mode_constant = 'WPBAY_' . strtoupper($this->product_slug) . '_DEVELOPER_MODE';
            $secret_key_constant = 'WPBAY_' . strtoupper($this->product_slug) . '_SECRET_KEY';
            if (defined($dev_mode_constant) && defined($secret_key_constant)) 
            {
                $secret = constant($secret_key_constant);
                if(constant($dev_mode_constant) === true && !empty($secret))
                {
                    $this->developer_mode = '1';
                }
                else
                {
                    $this->developer_mode = '0';
                }
                $this->secret_key = $secret;
            }
        }
        public function is_developer_mode()
        {
            if($this->developer_mode === '1')
            {
                return true;
            }
            return false;
        }
        public function is_debug_mode()
        {
            if($this->debug_mode === true)
            {
                return true;
            }
            return false;
        }
        private function define_globals() 
        {
            global $wpbay_sdk_dir;
            global $wpbay_sdk_url;
            $wpbay_sdk_dir = plugin_dir_path(__FILE__);
            $wpbay_sdk_url = plugin_dir_url(__FILE__);
        }
        private function define_constants() 
        {
            if (!defined('WPBAY_PURCHASE_CODE_ENCRYPTION_KEY')) 
            {
                define('WPBAY_PURCHASE_CODE_ENCRYPTION_KEY', '5fXCm5mEQHtJ9ESzSeC+3j2GMEXuIEuA0rtC9U5kO2s=');
            }
            if ( ! defined( 'WPBAY_LOWEST_PRIORITY' ) ) 
            {
                define( 'WPBAY_LOWEST_PRIORITY', 2147483647 );
            }
            if (!defined('WPBAY_IS_NETWORK_ADMIN')) 
            {
                define('WPBAY_IS_NETWORK_ADMIN',
                    is_multisite() &&
                    (is_network_admin() || defined('WP_UNINSTALL_PLUGIN'))
                );
            }
            if (!defined('WPBAY_IS_BLOG_ADMIN')) 
            {
                define('WPBAY_IS_BLOG_ADMIN', is_blog_admin());
            }
            if (!defined('WPBAY_TEMPLATES_PATH')) 
            {
                define('WPBAY_TEMPLATES_PATH', wpbay_sdk_normalize_path(dirname(__FILE__) . '/templates'));
            }
        }
        private function includes() 
        {
            global $wpbay_sdk_dir;
            require_once $wpbay_sdk_dir . 'License_Manager.php';
            require_once $wpbay_sdk_dir . 'Update_Manager.php';
            require_once $wpbay_sdk_dir . 'Analytics_Manager.php';
            require_once $wpbay_sdk_dir . 'Feedback_Manager.php';
            require_once $wpbay_sdk_dir . 'Admin_Notices.php';
            require_once $wpbay_sdk_dir . 'API_Manager.php';
            require_once $wpbay_sdk_dir . 'Purchase_Manager.php';
            require_once $wpbay_sdk_dir . 'Contact_Form_Manager.php';
            require_once $wpbay_sdk_dir . 'Menu_Manager.php';
        }
        private function init_hooks() 
        {
            if (!$this->is_free) 
            {
                add_action('wp_ajax_wpbay_sdk_purchase_code_actions' . sanitize_title($this->product_slug), array($this->license_manager, 'handle_ajax_requests'));
            }
            add_action('wp_ajax_wpbay_sdk_dismiss_admin_notice' . sanitize_title($this->product_slug), array($this->notice_manager, 'handle_dismiss_notice'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));

            if (is_multisite()) 
            {
                add_action('network_admin_menu', array($this, 'network_admin_menu'));
            }

            add_action('wp_ajax_wpbay_sdk_dismiss_notice' . sanitize_title($this->product_slug), array($this, 'dismiss_notice'));
            if($this->is_plugin()) 
            {
                // WordPress.org plugins are not allowed to act on theme activation/deactivation directly.
                // These hooks are only used for license tracking or optional analytics and are disabled for .org compliance.
                // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                if ( !$this->uploaded_to_wp_org ) 
                {
                    if (!$this->is_free) 
                    {
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('activate_' . $this->product_basename, array($this->license_manager, 'activate'));
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('deactivate_' . $this->product_basename, array($this->license_manager, 'deactivate'));
                    }
                    // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                    add_action('activate_' . $this->product_basename, array($this, 'activate_product_event_hook'));
                    
                    if (!$this->disable_analytics && !empty($this->wpbay_product_id) && !empty($this->api_key) && !empty($this->product_slug)) 
                    {
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('activate_' . $this->product_basename, array( $this->analytics_manager, 'track_activation' ));
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('deactivate_' . $this->product_basename, array( $this->analytics_manager, 'track_deactivation' ));
                    }
                }
                
            } 
            elseif($this->is_theme())
            {
                // WordPress.org plugins are not allowed to act on theme activation/deactivation directly.
                // These hooks are only used for license tracking or optional analytics and are disabled for .org compliance.
                // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                if ( !$this->uploaded_to_wp_org ) 
                {
                    if (!$this->is_free) 
                    {
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('after_switch_theme', array($this->license_manager, 'activate'));
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('switch_theme', array($this->license_manager, 'deactivate'));
                    }
                    // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                    add_action('after_switch_theme', array($this, 'activate_product_event_hook'));

                    if (!$this->disable_analytics && !empty($this->wpbay_product_id) && !empty($this->api_key) && !empty($this->product_slug)) 
                    {
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('after_switch_theme', array( $this->analytics_manager, 'track_activation' ));
                        // Disable these hooks when the SDK is used in a WordPress.org-hosted plugin
                        add_action('switch_theme', array( $this->analytics_manager, 'track_deactivation' ));
                    }
                }
            }

            if (!$this->disable_analytics && !empty($this->wpbay_product_id) && !empty($this->api_key) && !empty($this->product_slug)) 
            {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Custom error handler used safely in production for analytics
                set_error_handler(array($this->analytics_manager, 'handle_error'));
                register_shutdown_function(array($this->analytics_manager, 'handle_shutdown'));
                add_action('admin_init', array( $this->analytics_manager, 'handle_opt_in_response' ));
                add_action('upgrader_process_complete', array( $this->analytics_manager, 'handle_update' ), 10, 2);
            }
            self::$registered_hooks[] = $this;
        }
        private function is_user_admin() 
        {
            if ( is_multisite() && is_network_admin() ) 
            {
                return is_super_admin();
            }
            if ( current_user_can( is_multisite() ? 'manage_options' : 'activate_plugins' ) ) 
            {
                return true;
            }
            if ( current_user_can( 'switch_themes' ) ) 
            {
                return true;
            }
            return false;
        }
        private function is_product_activation() 
        {
            $result = get_transient( 'wpbay_sdk_'  . $this->product_type . '_' . $this->product_slug . '_activated' );
            return !empty($result);
        }
    }
    add_action('plugins_loaded', function() 
    {
        if(!is_admin())
        {
            return;
        }
        load_plugin_textdomain('wpbay-sdk', false, basename(dirname(__FILE__)) . '/languages');
    });
}
?>