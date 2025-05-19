<?php
namespace WPBaySDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class License_Manager 
{
    private static $instances = array();
    private static $initialized = false;

    private $purchase_code = '';
    private $activation_time = false;
    private $rating_shown = false;
    private $plan_type = '';
    // API endpoint used for license verification, activation, and revocation.
    // See WPBay_Loader.php or the SDK readme file for full details and data disclosure.
    private $api_endpoint = 'https://wpbay.com/api/purchase/v1/';
    private $option_name  = 'wpbay_sdk_license_data';
    private $api_key      = '';
    private $product_slug  = 'wpbay';
    private $product_name  = '';
    private $wpbay_product_id  = '';
    private $developer_mode = '0';
    private $secret_key     = '';
    private $this_sdk_version = '0.0.0';
    private $notice_manager = false;
    private $license_status_option;
    private $api_manager;
    private $debug_mode;

    private function __construct($product_slug, $product_name, $api_key, $dev_mode, $secret_key, $notice_manager, $wpbay_product_id, $this_sdk_version, $api_manager, $debug_mode) 
    {
        $this->product_slug = $product_slug;
        $product_info = $this->get_product_info();
        if(isset($product_info['purchase_code']))
        {
            $pc = wpbay_sdk_simple_decrypt($product_info['purchase_code']);
            if($pc !== false)
            {
                $this->purchase_code = $pc;
            }
        }
        if(isset($product_info['plan_type']))
        {
            $this->plan_type = $product_info['plan_type'];
        }
        if(isset($product_info['activation_time']))
        {
            $this->activation_time = $product_info['activation_time'];
        }
        if(isset($product_info['rating_shown']))
        {
            $this->rating_shown = $product_info['rating_shown'];
        }
        $this->license_status_option = 'wpbay_sdk_license_status_' . $product_slug;
        $this->api_key = $api_key;
        $this->developer_mode = $dev_mode;
        $this->secret_key = $secret_key;
        $this->notice_manager = $notice_manager;
        $this->wpbay_product_id = $wpbay_product_id;
        $this->this_sdk_version = $this_sdk_version;
        $this->api_manager = $api_manager;
        $this->debug_mode = $debug_mode;
        $this->product_name = $product_name;
        //license check once per day?
        add_action( 'wpbay_sdk_license_check_event_' . $this->product_slug, array( $this, 'check_license_status' ) );
    }
    public static function get_instance($product_slug, $product_name, $api_key, $dev_mode, $secret_key, $notice_manager, $wpbay_product_id, $this_sdk_version, $api_manager, $debug_mode) 
    {
        if (!isset(self::$instances[$product_slug])) 
        {
            self::$instances[$product_slug] = new self($product_slug, $product_name, $api_key, $dev_mode, $secret_key, $notice_manager, $wpbay_product_id, $this_sdk_version, $api_manager, $debug_mode);
        }
        return self::$instances[$product_slug];
    }

    /**
     * Call this method on plugin activation
     */
    public function activate() {
        if (!$this->is_user_admin()) 
        {
            return;
        }
        if ( ! wp_next_scheduled( 'wpbay_sdk_license_check_event_' . $this->product_slug ) ) {
            wp_schedule_event( time(), 'daily', 'wpbay_sdk_license_check_event_' . $this->product_slug );
        }
    }

    /**
     * Call this method on plugin deactivation
     */
    public function deactivate() {
        if (!$this->is_user_admin()) 
        {
            return;
        }
        wp_clear_scheduled_hook( 'wpbay_sdk_license_check_event_' . $this->product_slug );
    }

    public function check_license_status()
    {
        if ( empty( $this->purchase_code ) ) 
        {
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
            return;
        }
        $cached_status = $this->get_cached_license_status();
        if ( $cached_status && $cached_status !== 'unknown' ) 
        {
            return;
        }
        $api_url = $this->api_endpoint . 'verify';
        $request_data = array(
            'timeout' => 60,
            'body'    => array(
                'purchase_code'   => $this->purchase_code,
                'site_url'        => get_bloginfo('url'),
                'api_key'         => $this->api_key,
                'developer_mode'  => $this->developer_mode,
                'secret_key'      => $this->secret_key,
                'product_slug'    => $this->product_slug,
                'cachebust'       => wp_rand()
            ),
        );
        $response = $this->api_manager->post_request( $api_url, $request_data );
        if ( wpbay_sdk_is_error( $response ) ) 
        {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Failed to get license status!');
            }
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
            return;
        }
        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        if ( empty( $response_body ) ) 
        {
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
            return;
        }
        $result = json_decode( $response_body, true );
        if ( isset( $result['success'] ) && $result['success'] === true ) 
        {
            set_transient( $this->license_status_option, 'valid', DAY_IN_SECONDS );
        } 
        else 
        {
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
        }
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
    public function is_developer_mode()
    {
        if($this->developer_mode === '1')
        {
            return true;
        }
        return false;
    }
    public function admin_init() 
    {
        if($this->is_developer_mode() === true)
        {
            if ( ! self::$initialized ) 
            {
                global $wpbay_sdk_version;
                wp_enqueue_script( 'wpbay-admin-code-manager', plugins_url( '/scripts/purchase-code-manager.js', __FILE__ ), array( 'jquery' ), $this->this_sdk_version, true );
                wp_localize_script( 'wpbay-admin-code-manager', 'wpbay_sdk_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
                wp_enqueue_style(
                    'wpbay-license-manager-style',
                    plugin_dir_url( __FILE__ ) . 'styles/register.css',
                    array(),
                    $wpbay_sdk_version
                );
                self::$initialized = true;
            }
            $section_id = 'wpbay_sdk_license_section_' . $this->product_slug;
            global $wpbay_sdk_added_settings_sections;
            if (!isset($wpbay_sdk_added_settings_sections)) 
            {
                $wpbay_sdk_added_settings_sections = array();
            }
            if (in_array($section_id, $wpbay_sdk_added_settings_sections)) {
                return;
            }
            if (is_multisite())
            {
                $settings_name = 'wpbay-network-settings';
            }
            else
            {
                $settings_name = 'wpbay-settings';
            }
            add_settings_section(
                $section_id,
                esc_html(wpbay_get_text_inline( 'WPBay License', 'wpbay-sdk' )),
                null,
                $settings_name
            );
            add_settings_field(
                'wpbay_sdk_purchase_code' . $this->product_slug,
                esc_html(wpbay_get_text_inline( 'License Registration', 'wpbay-sdk' )) . '<br/>' . $this->product_slug,
                array( $this, 'purchase_code_field_callback' ),
                $settings_name,
                $section_id
            );
            register_setting( 'wpbay_sdk_settings', $this->option_name . $this->product_slug,
            array(
                'sanitize_callback' => 'sanitize_text_field'
            ) );
            
            $wpbay_sdk_added_settings_sections[] = $section_id;
        }
        $this->admin_notices();
    }
    public function purchase_code_field_callback() 
    {
        $purchase_code = $this->purchase_code;
        $product_slug = $this->product_slug;
        
        if ( $purchase_code ) 
        {
            echo '<div class="wpbay-sdk-register-form">';
            echo '<p class="description">' . esc_html(wpbay_get_text_inline( 'Your purchase code is registered.', 'wpbay-sdk' )) . '</p>';
            echo '<form method="post" class="wpbay-sdk-form">';
            echo '<table class="form-table"><tr><th scope="row">
            <strong>' . esc_html(wpbay_get_text_inline( 'Purchase Code:', 'wpbay-sdk' )) . '</strong></th><td><span class="wpbay_reveal_code">' . esc_html( $purchase_code ) . '</span></td></tr>';
            echo '<tr><td colspan="2"><input type="button" class="button wpbay-purchase-code-revoke" data-id="' . esc_attr($product_slug). '" value="' . esc_html(wpbay_get_text_inline( 'Revoke Code', 'wpbay-sdk' )) . '">&nbsp;
            <input type="button" class="button wpbay-purchase-code-registered" data-id="' . esc_attr($product_slug). '" value="' . esc_html(wpbay_get_text_inline( 'Code Is Registered?', 'wpbay-sdk' )) . '">&nbsp;
            <input type="button" class="button wpbay-purchase-code-check" data-id="' . esc_attr($product_slug). '" value="' . esc_html(wpbay_get_text_inline( 'Validate Code', 'wpbay-sdk' )) . '">
            <input type="hidden" id="wpbay_sdk_purchase_code' . esc_attr($product_slug). '" value="' . esc_attr( $purchase_code ) . '">
            <input type="hidden" id="wpbay_sdk_security' . esc_attr($product_slug). '" value="' . esc_attr(wp_create_nonce('wpbay_sdk_purchase_code_security')) . '"></td></tr></table>';
            echo '</form>';
            echo '</div>';
        } 
        else 
        {
            echo '<div class="wpbay-sdk-register-form">';
            echo '<p class="description">' . esc_html(wpbay_get_text_inline( 'Register your product by entering your WPBay.com purchase code below.', 'wpbay-sdk' )) . '</p>';
            echo '<form method="post" class="wpbay-sdk-form">';
            echo '<table class="form-table"><tr><th scope="row">';
echo '<label for="wpbay_sdk_purchase_code' . esc_attr($product_slug) . '">' . esc_html(wpbay_get_text_inline( 'Purchase Code:', 'wpbay-sdk' )) . '</label>';
echo '</th><td>';
echo '<input type="text" id="wpbay_sdk_purchase_code' . esc_attr($product_slug) . '" class="regular-text" placeholder="e.g., ABCDEF-1234567890AB-CDEFGHIJKLMN12-3456789" required>';
echo '<input type="hidden" id="wpbay_sdk_security' . esc_attr($product_slug). '" value="' . esc_attr(wp_create_nonce('wpbay_sdk_purchase_code_security')) . '">';
echo '</td></tr></table>';
            echo '<p><input type="button" data-id="' . esc_attr($product_slug). '" class="button button-primary wpbay-purchase-code-register" value="' . esc_html(wpbay_get_text_inline( 'Register', 'wpbay-sdk' )) . '"></p>';
            echo '</form>';
            echo '</div>';
        }
    }

    public function is_plan($plan_name) 
    {
        $get_plan_type = $this->plan_type;
        if(!empty($get_plan_type) && $get_plan_type === $plan_name)
        {
            return true;
        }
        return false;
    }
    public function handle_ajax_requests()
    {
        $wpbay_sdk_result = array('status' => 'error', 'message' => 'Something went wrong with the purchase code verification');
        if ( !isset($_POST['wpbay_sdk_security']) || !wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['wpbay_sdk_security'] )), 'wpbay_sdk_purchase_code_security' ) ) {
            $wpbay_sdk_result['message'] = 'You are not allowed to execute this action!';
            wp_send_json($wpbay_sdk_result);
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            $wpbay_sdk_result['message'] = 'You are not allowed to perform this action.';
            wp_send_json( $wpbay_sdk_result );
        }
        if(!isset($_POST['wpbay_sdk_action']))
        {
            $wpbay_sdk_result['message'] = 'Incorrect request';
            wp_send_json($wpbay_sdk_result);
        }
        if(!isset($_POST['purchase_code']))
        {
            $wpbay_sdk_result['message'] = 'Incorrect request sent';
            wp_send_json($wpbay_sdk_result);
        }
        $product_slug = '';
        if(isset($_POST['wpbay_slug']))
        {
            $product_slug = sanitize_text_field(wp_unslash( $_POST['wpbay_slug'] ));
        }
        if($_POST['wpbay_sdk_action'] == 'register')
        {
            $purchase_code = isset($_POST['purchase_code']) && !empty($_POST['purchase_code']) ? sanitize_text_field(wp_unslash($_POST['purchase_code'])) : '';
            if(empty($purchase_code))
            {
                $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
                wp_send_json($wpbay_sdk_result);
            }
            $rez = $this->register_purchase_code($purchase_code, $product_slug);
            if ( $rez['status'] === 'success' ) 
            {
                $wpbay_sdk_result['message'] = 'Purchase code registered successfully.';
                $wpbay_sdk_result['status'] = 'success';
                wp_send_json($wpbay_sdk_result);
            } 
            else 
            {
                $wpbay_sdk_result['message'] = $rez['message'];
                wp_send_json($wpbay_sdk_result);
            }
        }
        elseif($_POST['wpbay_sdk_action'] == 'revoke')
        {
            $purchase_code = isset($_POST['purchase_code']) && !empty($_POST['purchase_code']) ? sanitize_text_field(wp_unslash($_POST['purchase_code'])) : '';
            if(empty($purchase_code))
            {
                $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
                wp_send_json($wpbay_sdk_result);
            }
            $rez = $this->revoke_purchase_code($purchase_code, $product_slug);
            if ( $rez['status'] === 'success' ) 
            {
                $wpbay_sdk_result['message'] = 'Purchase code revoked successfully.';
                $wpbay_sdk_result['status'] = 'success';
                delete_transient( $this->license_status_option );
                wp_send_json($wpbay_sdk_result);
            } 
            else 
            {
                $wpbay_sdk_result['message'] = $rez['message'];
                wp_send_json($wpbay_sdk_result);
            }
        }
        elseif($_POST['wpbay_sdk_action'] == 'check')
        {
            $purchase_code = isset($_POST['purchase_code']) && !empty($_POST['purchase_code']) ? sanitize_text_field(wp_unslash($_POST['purchase_code'])) : '';
            if(empty($purchase_code))
            {
                $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
                wp_send_json($wpbay_sdk_result);
            }
            $rez = $this->check_purchase_code($purchase_code);
            if ( $rez['status'] === 'success' ) 
            {
                $wpbay_sdk_result['message'] = 'Purchase code checked successfully.';
                $wpbay_sdk_result['status'] = 'success';
                wp_send_json($wpbay_sdk_result);
            } 
            else 
            {
                $wpbay_sdk_result['message'] = $rez['message'];
                wp_send_json($wpbay_sdk_result);
            }
        }
        elseif($_POST['wpbay_sdk_action'] == 'registered')
        {
            $purchase_code = isset($_POST['purchase_code']) && !empty($_POST['purchase_code']) ? sanitize_text_field(wp_unslash($_POST['purchase_code'])) : '';
            if(empty($purchase_code))
            {
                $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
                wp_send_json($wpbay_sdk_result);
            }
            $rez = $this->check_purchase_code_registered($purchase_code);
            if ( $rez['status'] === 'success' ) 
            {
                $wpbay_sdk_result['message'] = 'Purchase code is registered.';
                $wpbay_sdk_result['status'] = 'success';
                wp_send_json($wpbay_sdk_result);
            } 
            else 
            {
                $wpbay_sdk_result['message'] = $rez['message'];
                wp_send_json($wpbay_sdk_result);
            }
        }
        else
        {
            $wpbay_sdk_result['message'] = 'Incorrect request submitted';
            wp_send_json($wpbay_sdk_result);
        }
    }

    public function get_api_key() 
    {
        return $this->api_key;
    }
    public function get_sdk_version() 
    {
        return $this->this_sdk_version;
    }
    public function get_product_info() 
    {
        $product_info = get_site_option( $this->option_name . $this->product_slug, array() );
        return $product_info;
    }
    public function get_plan_type() 
    {
        $product_info = get_site_option( $this->option_name . $this->product_slug, array() );
        if(is_array($product_info) && isset($product_info['plan_type']))
        {
            $product_info['plan_type'];
        }
        return '';
    }
    public function get_purchase_code() 
    {
        //todo remove dev purchase code if dev mode
        return $this->purchase_code;
    }
    public function get_rating_shown() 
    {
        return $this->rating_shown;
    }
    public function get_activation_time() 
    {
        return $this->activation_time;
    }
    public function get_developer_mode() 
    {
        return $this->developer_mode;
    }
    public function get_secret_key() 
    {
        return $this->secret_key;
    }
    public function set_rating_shown($product_slug) 
    {
        $product_info = get_site_option( $this->option_name . $product_slug, false );
        if(!empty($product_info) && is_array($product_info))
        {
            $product_info['rating_shown'] = true;
            update_site_option( $this->option_name . $product_slug, $product_info );
        }
        return true;
    }

    private function set_purchase_code( $purchase_code, $plan_type, $product_slug ) 
    {
        $pc = wpbay_sdk_simple_encrypt($purchase_code);
        if($pc === false)
        {
            return false;
        }
        $product_info = array('purchase_code' => $pc, 'plan_type' => $plan_type, 'activation_time' => time());
        update_site_option( $this->option_name . $product_slug, $product_info );
    }

    private function remove_purchase_code($product_slug) 
    {
        delete_site_option( $this->option_name . $product_slug );
    }

    private function register_purchase_code( $purchase_code, $product_slug ) 
    {
        $wpbay_sdk_result = array('status' => 'error', 'message' => 'Something went wrong with the purchase code verification');
        $api_url = $this->api_endpoint . 'register';

        if ( empty( $purchase_code ) ) {
            $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
            return $wpbay_sdk_result;
        }
        if ( empty( $this->api_key ) ) {
            $wpbay_sdk_result['message'] = 'WPBay SDK is not correctly set up. Contact the plugin\'s developer and report this issue.';
            return $wpbay_sdk_result;
        }
        $pargs = array(
            'timeout' => 60,
            'body'    => array(
                'purchase_code'   => $purchase_code,
                'admin_email'     => get_bloginfo('admin_email'),
                'site_url'        => get_bloginfo('url'),
                'api_key'         => $this->api_key,
                'product_slug'    => $product_slug,
                'wpbay_product_id'=> $this->wpbay_product_id,
                'developer_mode'  => $this->developer_mode,
                'secret_key'      => $this->secret_key,
                'cachebust'       => wp_rand()
            )
        );
        $response = $this->api_manager->post_request( $api_url, $pargs );

        if ( wpbay_sdk_is_error( $response ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Error in purchase code registration!');
            }
            $wpbay_sdk_result['message'] = 'Unable to connect to the WPBay server. Please try again.';
            return $wpbay_sdk_result;
        }

        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        if(empty($response_body))
        {
            $wpbay_sdk_result['message'] = 'An error occurred during license activation.';
            return $wpbay_sdk_result;
        }
        $result = json_decode( $response_body, true );

        if ( isset( $result['success'] ) && isset( $result['plan_type'] ) && !empty( $result['plan_type'] ) && $result['success'] === true ) {
            $registration_status = $this->set_purchase_code( $purchase_code, $result['plan_type'], $product_slug );
            if($registration_status === false)
            {
                $wpbay_sdk_result['message'] = 'Failed to register the purchase code on your server.';
                return $wpbay_sdk_result;
            }
            else
            {
                $wpbay_sdk_result['message'] = 'License activated successfully.';
                $wpbay_sdk_result['plan_type'] = $result['plan_type'];
                $wpbay_sdk_result['status'] = 'success';
                return $wpbay_sdk_result;
            }
        } elseif(isset($result['message'])) {
            $wpbay_sdk_result['message'] = $result['message'];
            return $wpbay_sdk_result;
        }
        else
        {
            $wpbay_sdk_result['message'] = 'An unexpected error occurred.';
            return $wpbay_sdk_result;
        }
    }

    private function revoke_purchase_code($purchase_code, $product_slug) 
    {
        $wpbay_sdk_result = array('status' => 'error', 'message' => 'Something went wrong with the purchase code revoking');
        $api_url = $this->api_endpoint . 'revoke';

        if ( empty( $purchase_code ) ) {
            $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
            return $wpbay_sdk_result;
        }
        if ( empty( $this->api_key ) ) {
            $wpbay_sdk_result['message'] = 'WPBay SDK is not correctly set up. Contact the plugin\'s developer and report this issue.';
            return $wpbay_sdk_result;
        }
        $pargs = array(
            'timeout' => 60,
            'body'    => array(
                'purchase_code'   => $purchase_code,
                'admin_email'     => get_bloginfo('admin_email'),
                'site_url'        => get_bloginfo('url'),
                'api_key'         => $this->api_key,
                'product_slug'    => $product_slug,
                'developer_mode'  => $this->developer_mode,
                'secret_key'      => $this->secret_key,
                'cachebust'       => wp_rand()
            )
        );
        $response = $this->api_manager->post_request( $api_url, $pargs );

        if ( wpbay_sdk_is_error( $response ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Error in purchase code revoking!');
            }
            $wpbay_sdk_result['message'] = 'Unable to connect to the WPBay server. Please try again.';
            return $wpbay_sdk_result;
        }

        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        if(empty($response_body))
        {
            $wpbay_sdk_result['message'] = 'An error occurred during license revoking.';
            return $wpbay_sdk_result;
        }
        $result = json_decode( $response_body, true );

        if ( isset( $result['success'] ) && $result['success'] === true ) {
            $this->remove_purchase_code($product_slug);
            $wpbay_sdk_result['message'] = 'License revoked successfully.';
            $wpbay_sdk_result['status'] = 'success';
            delete_transient( $this->license_status_option );
            return $wpbay_sdk_result;
        } else {
            if(isset($result['code']) && $result['code'] === 'sandbox_not_found')
            {
                $this->remove_purchase_code($product_slug);
                $wpbay_sdk_result['message'] = 'License revoked successfully from local machine.';
                $wpbay_sdk_result['status'] = 'success';
                delete_transient( $this->license_status_option );
                return $wpbay_sdk_result;
            }
            else
            {
                $wpbay_sdk_result['message'] = $result['message'];
                return $wpbay_sdk_result;
            }
        }
    }
    private function check_purchase_code($purchase_code) 
    {
        $wpbay_sdk_result = array('status' => 'error', 'message' => 'Something went wrong with the purchase code checking');
        $api_url = $this->api_endpoint . 'verify';

        if ( empty( $purchase_code ) ) {
            $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
            return $wpbay_sdk_result;
        }
        if ( empty( $this->api_key ) ) {
            $wpbay_sdk_result['message'] = 'WPBay SDK is not correctly set up. Contact the plugin\'s developer and report this issue.';
            return $wpbay_sdk_result;
        }
        $arguments = array(
            'timeout' => 60,
            'body'    => array(
                'purchase_code'   => $purchase_code,
                'site_url'        => get_bloginfo('url'),
                'api_key'         => $this->api_key,
                'developer_mode'  => $this->developer_mode,
                'secret_key'      => $this->secret_key,
                'product_slug'    => $this->product_slug,
                'cachebust'       => wp_rand()
            ),
        );
        $response = $this->api_manager->post_request( $api_url, $arguments );

        if ( wpbay_sdk_is_error( $response ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Error in purchase code checking!');
            }
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
            $wpbay_sdk_result['message'] = 'Unable to connect to the WPBay server. Please try again.';
            return $wpbay_sdk_result;
        }

        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        if(empty($response_body))
        {
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
            $wpbay_sdk_result['message'] = 'An error occurred during license revoking.';
            return $wpbay_sdk_result;
        }
        $result        = json_decode( $response_body, true );
        if (( isset( $result['success'] ) && $result['success'] === true ) || (isset( $result['order_status'] ) && $result['order_status'] === 'completed' && isset( $result['product_id'] ) && is_numeric($result['product_id']))) 
        {
            set_transient( $this->license_status_option, 'valid', DAY_IN_SECONDS );
            $wpbay_sdk_result['message'] = 'valid';
            $wpbay_sdk_result['status'] = 'success';
            return $wpbay_sdk_result;
        } 
        else 
        {
            set_transient( $this->license_status_option, 'invalid', DAY_IN_SECONDS );
            $wpbay_sdk_result['message'] = $result['message'];
            return $wpbay_sdk_result;
        }
    }
    private function get_cached_license_status() 
    {
        $license_status = get_transient( $this->license_status_option );
        if ( false === $license_status ) 
        {
            $license_status = 'unknown';
        }
        return $license_status;
    }
    private function check_purchase_code_registered($purchase_code) 
    {
        $wpbay_sdk_result = array('status' => 'error', 'message' => 'Something went wrong with the purchase code checking');
        $api_url = $this->api_endpoint . 'registered';

        if ( empty( $purchase_code ) ) {
            $wpbay_sdk_result['message'] = 'You need to enter a purchase code for this to work.';
            return $wpbay_sdk_result;
        }
        if ( empty( $this->api_key ) ) {
            $wpbay_sdk_result['message'] = 'WPBay SDK is not correctly set up. Contact the plugin\'s developer and report this issue.';
            return $wpbay_sdk_result;
        }
        $arguments = array(
            'timeout' => 60,
            'body'    => array(
                'purchase_code'   => $purchase_code,
                'site_url'        => get_bloginfo('url'),
                'api_key'         => $this->api_key,
                'developer_mode'  => $this->developer_mode,
                'secret_key'      => $this->secret_key,
                'product_slug'    => $this->product_slug,
                'cachebust'       => wp_rand()
            ),
        );
        $response = $this->api_manager->post_request( $api_url, $arguments );
        if ( wpbay_sdk_is_error( $response ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Error in check if purchase code is registered!');
            }
            $wpbay_sdk_result['message'] = 'Unable to connect to the WPBay server. Please try again.';
            return $wpbay_sdk_result;
        }

        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        if(empty($response_body))
        {
            $wpbay_sdk_result['message'] = 'An error occurred during license revoking.';
            return $wpbay_sdk_result;
        }
        $result        = json_decode( $response_body, true );
        if ( isset( $result['success'] ) && $result['success'] === true ) {
            $wpbay_sdk_result['message'] = $result['message'];
            $wpbay_sdk_result['status'] = 'success';
            return $wpbay_sdk_result;
        } else {
            $wpbay_sdk_result['message'] = $result['message'];
            return $wpbay_sdk_result;
        }
    }
    public function validate_license() {
        $purchase_code = $this->purchase_code;
        if ( empty( $purchase_code ) ) {
            return false;
        }

        $api_url = $this->api_endpoint . 'verify';
        $arguments = array(
            'timeout' => 60,
            'body'    => array(
                'purchase_code'   => $purchase_code,
                'site_url'        => get_bloginfo('url'),
                'api_key'         => $this->api_key,
                'developer_mode'  => $this->developer_mode,
                'secret_key'      => $this->secret_key,
                'product_slug'    => $this->product_slug,
                'cachebust'       => wp_rand()
            ),
        );
        $response = $this->api_manager->post_request( $api_url, $arguments );

        if ( wpbay_sdk_is_error( $response ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Failed to validate license!');
            }
            return false;
        }

        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        $result        = json_decode( $response_body, true );

        if ( isset( $result['valid'] ) && $result['valid'] ) {
            return true;
        } else {
            return false;
        }
    }
    
    public function admin_notices() 
    {
        if ( empty($this->get_purchase_code()) ) 
        {
            $license_notice = sprintf( wp_kses( 
                // translators: %1$s: Product ID, %2$s: Product name
                wpbay_get_text_inline( "Your license is not active for: <a href=\"https://wpbay.com/?p=%1\$s\" target=\"_blank\"><strong>%2\$s</strong></a>. Please activate your license to use this product.", 'wpbay-sdk'),
                     array('strong' => array(),'a' => array('href' => array(),'target' => array())) ), esc_html($this->wpbay_product_id), esc_html($this->product_name) );
            $license_notice = apply_filters( 'wpbay_sdk_activate_license_notice', $license_notice );
            $this->notice_manager->add_notice($license_notice, 'warning');
        }
    }
}
