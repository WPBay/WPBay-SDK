<?php
namespace WPBaySDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Admin_Notice_Manager' ) ) 
{
    class Admin_Notice_Manager 
    {
        private static $instances = array(); 
        private static $initialized = false;
        private $notices = array();
        private $option_name = '';  
        private $slug = ''; 
        private $debug_mode = false;
        
        private function __construct( $slug, $debug_mode ) 
        {
            $this->slug = $slug;
            $this->debug_mode = $debug_mode;
            $this->option_name = 'wpbay_sdk_admin_notices_' . $this->slug;  
            add_action( 'admin_notices', array( $this, 'display_notices' ) );
            add_action( 'admin_init', array( $this, 'load_notices' ) );
            if ( ! self::$initialized ) 
            {
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_dismiss_script' ) );
                self::$initialized = true;
            }
        }
        public static function get_instance( $slug, $debug_mode ) 
        {
            if ( ! isset( self::$instances[ $slug ] ) ) {
                self::$instances[ $slug ] = new self( $slug, $debug_mode );
            }
            return self::$instances[ $slug ];
        }
        public function load_notices() 
        {
            $notice_value = get_transient( $this->option_name );
            if($notice_value === false)
            {
                $notice_value = array();
            }
            $this->notices = $notice_value;
        }
        public static function enqueue_dismiss_script() 
        {
            global $wpbay_sdk_version;
            wp_enqueue_script( 'wpbay-admin-notice-manager', plugins_url( '/scripts/admin-notice-manager.js', __FILE__ ), array( 'jquery' ), $wpbay_sdk_version, true );
            wp_localize_script( 'wpbay-admin-notice-manager', 'wpbay_sdk_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'wpbay_sdk_dismiss_notice' ) ) );
        }
        public function add_notice( $message, $type = 'info', $sticky = false ) 
        {
            $notice = array(
                'message' => $message,
                'type'    => $type,
                'sticky'  => $sticky,
                'slug'    => $this->slug, 
            );
            if ( ! in_array( $notice, $this->notices ) ) 
            {
                $this->notices[] = $notice;
                $this->save_notices();
            }
        }
        public function remove_notice( $message ) 
        {
            $this->notices = array_filter( $this->notices, function( $notice ) use ( $message ) {
                return $notice['message'] !== $message || $notice['slug'] !== $this->slug;
            });
            $this->save_notices();
        }
        public function clear_notices() 
        {
            $this->notices = array();
            $this->save_notices();
        }
        private function save_notices() 
        {
            set_transient( $this->option_name, $this->notices, 3600 );
        }
        public function display_notices() 
        {
            foreach ( $this->notices as $key => $notice ) 
            {
                if ( $notice['slug'] !== $this->slug ) {
                    continue;
                }
                $notice['message'] = apply_filters( 'wpbay_sdk_admin_notice_filter', $notice['message'], $key, $this->slug );
                if(empty($notice['message']))
                {
                    continue;
                }
                $class = 'wpbay-notice notice notice-' . esc_attr( $notice['type'] );
                if ( ! $notice['sticky'] ) {
                    $class .= ' is-dismissible';
                }
                echo '<div class="' . esc_attr($class) . '" data-notice-key="' . esc_attr($key) . '" data-slug="' . esc_attr($this->slug) . '">';
                
                echo '<p>' . wp_kses( $notice['message'], array( 
                    'strong' => array(), 
                    'em' => array(), 
                    'b' => array(), 
                    'i' => array(), 
                    'a' => array( 'href' => array(), 'target' => array() ) 
                ) ) . '</p>';
                echo '</div>';
            }
        }
        public function handle_dismiss_notice() 
        {
            check_ajax_referer( 'wpbay_sdk_dismiss_notice' );
            if(!current_user_can( 'edit_posts' ))
            {
                return;
            }
            $key = isset( $_POST['notice_key'] ) ? intval( $_POST['notice_key'] ) : false;
            if ( $key !== false && isset( $this->notices[ $key ] ) ) {
                unset( $this->notices[ $key ] );
                $this->save_notices();
                wp_send_json_success();
            }
            wp_send_json_error();
        }
    }
}
?>