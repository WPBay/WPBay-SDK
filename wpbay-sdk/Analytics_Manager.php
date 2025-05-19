<?php
namespace WPBaySDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Analytics_Manager 
{
    private static $instances = array();
    private $product_slug;
    private $product_id;
    private $api_manager;
    private $license_manager;
    private $notice_manager;
    private $type; // 'plugin' or 'theme'
    // API endpoint used to submit optional, anonymized analytics data to WPBay.com.
    // See WPBay_Loader.php or the SDK readme file for full disclosure of what is sent and when.
    private $api_endpoint = 'https://wpbay.com/api/analytics/v1/submit';
    private $opted_in;
    private $consent_shown;
    private $debug_mode;

    private function __construct( $product_slug, $api_manager, $license_manager, $notice_manager, $product_id, $type = 'plugin', $debug_mode = false ) 
    {
        $this->product_slug    = $product_slug;
        $this->product_id      = $product_id;
        $this->api_manager     = $api_manager;
        $this->license_manager = $license_manager;
        $this->notice_manager  = $notice_manager;
        $this->type            = $type;
        $this->debug_mode      = $debug_mode;
        $this->opted_in        = get_option( "wpbay_sdk_{$this->product_slug}_analytics_opt_in", false );
        $this->consent_shown   = get_option( "wpbay_sdk_{$this->product_slug}_analytics_consent_shown", false );
        if ( $this->consent_shown !== '1' ) 
        {
            $this->show_opt_in_notice();
        }
        
        add_action( 'admin_init', array( $this, 'send_batched_events' ) );
    }

    public static function get_instance( $product_slug, $api_manager, $license_manager, $notice_manager, $product_id, $type = 'plugin', $debug_mode = false ) 
    {
        if (!isset(self::$instances[$product_slug])) 
        {
            self::$instances[$product_slug] = new self( $product_slug, $api_manager, $license_manager, $notice_manager, $product_id, $type, $debug_mode );
        }
        return self::$instances[$product_slug];
    }

    public function log_event($event_name, $event_data = array()) 
    {
        if (empty($this->product_slug)) return;
        if (empty($this->product_id)) return;
        if ( ! $this->opted_in ) {
            return; 
        }

        $events = get_option("wpbay_sdk_{$this->product_slug}_analytics_log", array());
        $events[] = array_merge(
            $event_data,
            array(
                'event_name'   => $event_name,
                'timestamp'    => time(),
                'wp_version'   => get_bloginfo('version'),
                'php_version'  => PHP_VERSION,
                'sdk_version' => $this->license_manager->get_sdk_version(),
                'theme'        => wp_get_theme()->get('Name'),
            )
        );
        update_option("wpbay_sdk_{$this->product_slug}_analytics_log", $events, false);
    }

    public function send_batched_events() 
    {
        if (empty($this->product_slug)) return;
        if (empty($this->product_id)) return;
        $events = get_option("wpbay_sdk_{$this->product_slug}_analytics_log", array());
        if (empty($events)) return;

        $args = array(
            'body'    => json_encode(array(
                'api_key'       => $this->license_manager->get_api_key(),
                'purchase_code' => $this->license_manager->get_purchase_code(),
                'product_slug'  => $this->product_slug,
                'product_id'    => $this->product_id,
                'events'        => $events,
                'site_url'      => home_url(),
                'developer_mode'=> $this->license_manager->get_developer_mode(),
                'secret_key'    => $this->license_manager->get_secret_key()
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 90,
        );

        $response = wp_remote_post( $this->api_endpoint, $args );

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            delete_option("wpbay_sdk_{$this->product_slug}_analytics_log"); 
        }
        else
        {
            if($this->debug_mode === true)
            {
                if(is_wp_error($response))
                {
                    wpbay_log_to_file('Failed to send batched event: ' . $response->get_error_message());
                }
                else
                {
                    wpbay_log_to_file('Invalid response code from batched event sending: ' . wp_remote_retrieve_response_code($response));
                }
            }
        }
    }

    private function is_relevant_error($file) 
    {
        return strpos($file, $this->product_slug) !== false;
    }

    public function handle_error($severity, $message, $file, $line) 
    {
        if ($this->is_relevant_error($file)) {
            $error_type = match ($severity) {
                E_USER_ERROR => 'User Error',
                E_WARNING, E_USER_WARNING => 'Warning',
                E_NOTICE, E_USER_NOTICE => 'Notice',
                E_STRICT => 'Strict',
                E_RECOVERABLE_ERROR => 'Recoverable Error',
                default => 'Unknown',
            };

            $this->log_event('error', array(
                'type'    => $error_type,
                'message' => $message,
                'file'    => $file,
                'line'    => $line,
            ));
        }
    }

    public function handle_shutdown() 
    {
        $last_error = error_get_last();
        if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if ($this->is_relevant_error($last_error['file'])) {
                $this->log_event('fatal_error', array(
                    'message' => $last_error['message'],
                    'file'    => $last_error['file'],
                    'line'    => $last_error['line'],
                ));
            }
        }
    }

    public function opt_in() {
        $this->opted_in = true;
        update_option( "wpbay_sdk_{$this->product_slug}_analytics_opt_in", true, false );
    }

    public function opt_out() {
        $this->opted_in = false;
        update_option( "wpbay_sdk_{$this->product_slug}_analytics_opt_in", false, false );
    }

    public function is_opted_in() {
        return $this->opted_in;
    }

    public function show_opt_in_notice() 
    {
        $nonce = wp_create_nonce( 'wpbay_sdk_opt_in_' . $this->product_slug );
        $notice_content = '<p><b>' . esc_html( $this->product_slug ) . ':</b> ' . esc_html(wpbay_get_text_inline( 'We would like to collect anonymous usage data to help improve this product. No personal information will be collected.', 'wpbay-sdk' )) . '</p>
<p><button class="button button-primary" onclick="location.href=\'' . esc_url( add_query_arg( array( 'wpbay_sdk_analytics_opt_in' => 'yes', 'wpbay_slug' => $this->product_slug, '_wpnonce' => $nonce ) ) ) . '\'">' . esc_html(wpbay_get_text_inline( 'Allow', 'wpbay-sdk' )) . '</button>
<button class="button" onclick="location.href=\'' . esc_url( add_query_arg( array( 'wpbay_sdk_analytics_opt_in' => 'no', 'wpbay_slug' => $this->product_slug, '_wpnonce' => $nonce ) ) ) . '\'">' . esc_html(wpbay_get_text_inline( 'No, thanks', 'wpbay-sdk' )) . '</button>
</p>';
        $this->notice_manager->add_notice($notice_content, 'info');
    }

    public function handle_opt_in_response() 
    {
        if ( isset( $_GET['_wpnonce'] ) && isset( $_GET['wpbay_sdk_analytics_opt_in'] ) && isset( $_GET['wpbay_slug'] ) && $_GET['wpbay_slug'] === $this->product_slug && 
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified via wp_verify_nonce()
        wp_verify_nonce( (string) wp_unslash($_GET['_wpnonce']), 'wpbay_sdk_opt_in_' . $this->product_slug ) ) {
            $opt_in_choice = sanitize_text_field( wp_unslash($_GET['wpbay_sdk_analytics_opt_in']) );
            if ( 'yes' === $opt_in_choice ) {
                $this->opt_in();
            } else {
                $this->opt_out();
            }
            update_option( "wpbay_sdk_{$this->product_slug}_analytics_consent_shown", '1' );
            wp_redirect( remove_query_arg( array( 'wpbay_sdk_analytics_opt_in', 'wpbay_slug', '_wpnonce' ) ) );
            exit;
        }
    }

    public function handle_update( $upgrader_object, $options ) 
    {
        if ( $this->type === 'plugin' && $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            $plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
            foreach ( $plugins as $plugin ) {
                if ( strpos( $plugin, $this->product_slug ) !== false ) {
                    $this->track_update('plugin');
                    break;
                }
            }
        }

        if ( $this->type === 'theme' && $options['action'] === 'update' && $options['type'] === 'theme' ) {
            $themes = isset( $options['themes'] ) ? (array) $options['themes'] : array();
            foreach ( $themes as $theme ) {
                if ( strpos( $theme, $this->product_slug ) !== false ) {
                    $this->track_update('theme');
                    break;
                }
            }
        }
    }

    public function track_activation() 
    {
        $this->log_event('activation', array());
    }

    public function track_deactivation() 
    {
        $this->log_event('deactivation', array());
    }

    public function track_update($type) 
    {
        $this->log_event('update', array('type' => $type));
    }
}
