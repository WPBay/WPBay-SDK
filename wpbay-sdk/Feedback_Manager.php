<?php
namespace WPBaySDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class Feedback_Manager 
{
    private static $instances = array();
    private static $feedback_manager_data = array();
    private $product_slug;
    private $api_manager;
    private $license_manager;
    private $product_file;
    private $product_basename;
    private $debug_mode;
    private $wpbay_product_id;
    // API endpoint for submitting user feedback and support requests to WPBay.com.
    // For full data handling details, see WPBay_Loader.php or the SDK readme file.
    private $api_endpoint = 'https://wpbay.com/api/feedback/v1/';

    private function __construct( $product_slug, $api_manager, $license_manager, $product_file, $wpbay_product_id, $debug_mode ) 
    {
        $this->product_slug       = $product_slug;
        $this->api_manager        = $api_manager;
        $this->license_manager    = $license_manager;
        $this->product_file       = $product_file;
        $this->debug_mode         = $debug_mode;
        $this->product_basename   = plugin_basename($this->product_file);
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_wpbay_sdk_submit_feedback', array( $this, 'handle_feedback_submission' ) );
    }

    public static function get_instance( $product_slug, $api_manager, $license_manager, $product_file, $wpbay_product_id, $debug_mode ) 
    {
        if (!isset(self::$instances[$product_slug])) 
        {
            self::$instances[$product_slug] = new self( $product_slug, $api_manager, $license_manager, $product_file, $wpbay_product_id, $debug_mode );
        }
        return self::$instances[$product_slug];
    }

    public function enqueue_scripts( $hook_suffix ) 
    {
        if ( 'plugins.php' !== $hook_suffix && 'themes.php' !== $hook_suffix && 'theme-install.php' !== $hook_suffix && 'update.php' !== $hook_suffix ) {
            return;
        }
        wp_enqueue_script(
            'wpbay-feedback-manager-script-' . $this->product_slug,
            plugin_dir_url( __FILE__ ) . 'scripts/feedback-manager.js',
            array( 'jquery', 'jquery-ui-dialog' ),
            $this->license_manager->get_sdk_version(),
            true
        );
        $is_active_theme = false;
        if ( 'themes.php' === $hook_suffix || 'theme-install.php' === $hook_suffix || 'update.php' === $hook_suffix ) {
            // For themes, check if the current theme's stylesheet matches the product slug
            $is_active_theme = ( wp_get_theme()->get_stylesheet() === $this->product_slug );
        }
        $feedback_title = esc_html(wpbay_get_text_inline( 'Quick Feedback', 'wpbay-sdk' ));
        $feedback_title = apply_filters('wpbay_sdk_feedback_text_title', $feedback_title);
        $feedback_title = esc_html($feedback_title);
        
        $dialog_message = esc_html(wpbay_get_text_inline( 'Before you deactivate, please let us know why:', 'wpbay-sdk' ));
        $dialog_message = apply_filters('wpbay_sdk_feedback_text_message', $dialog_message);
        $dialog_message = esc_html($dialog_message);

        $details_prompt = esc_html(wpbay_get_text_inline( 'We appreciate your feedback to help us improve.', 'wpbay-sdk' ));
        $details_prompt = apply_filters('wpbay_sdk_feedback_text_prompt', $details_prompt);
        $details_prompt = esc_html($details_prompt);

        $select_reason = esc_html(wpbay_get_text_inline( 'Please select a reason for deactivating.', 'wpbay-sdk' ));
        $select_reason = apply_filters('wpbay_sdk_feedback_text_reason', $select_reason);
        $select_reason = esc_html($select_reason);
        if($is_active_theme)
        {
            self::$feedback_manager_data[$this->product_slug] = array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'wpbay_sdk_submit_feedback_nonce' ),
                'product_slug'  => $this->product_slug,
                'reasons'       => $this->get_feedback_reasons(),
                'dialog_title'  => $feedback_title,
                'dialog_message'=> $dialog_message,
                'details_prompt'=> $details_prompt,
                'select_reason' => $select_reason,
                'product_file'  => $this->product_file,
                'is_active_theme' => $is_active_theme,
                'product_id'    => $this->wpbay_product_id
            );
        }
        else
        {
            self::$feedback_manager_data[$this->product_basename] = array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'wpbay_sdk_submit_feedback_nonce' ),
                'product_slug'  => $this->product_slug,
                'reasons'       => $this->get_feedback_reasons(),
                'dialog_title'  => $feedback_title,
                'dialog_message'=> $dialog_message,
                'details_prompt'=> $details_prompt,
                'select_reason' => $select_reason,
                'product_file'  => $this->product_file,
                'is_active_theme' => $is_active_theme,
                'product_id'    => $this->wpbay_product_id
            );
        }
        wp_localize_script( 'wpbay-feedback-manager-script-' . $this->product_slug, 'FeedbackManagerData', self::$feedback_manager_data);

        wp_enqueue_style(
            'wpbay-feedback-manager-style',
            plugin_dir_url( __FILE__ ) . 'styles/feedback-manager.css',
            array(),
            $this->license_manager->get_sdk_version()
        );
    }

    private function get_feedback_reasons() {
        $feedback_reasons = array(
            'technical_issues'   => esc_html(wpbay_get_text_inline( 'I encountered technical problems', 'wpbay-sdk' )),
            'found_alternative'  => esc_html(wpbay_get_text_inline( 'I found an alternative solution', 'wpbay-sdk' )),
            'feature_missing'    => esc_html(wpbay_get_text_inline( 'It lacks a feature I need', 'wpbay-sdk' )),
            'temporary_deactivation' => esc_html(wpbay_get_text_inline( 'Just temporarily deactivating', 'wpbay-sdk' )),
            'no_longer_required' => esc_html(wpbay_get_text_inline( 'I no longer require this plugin', 'wpbay-sdk' )),
            'setup_difficulty'   => esc_html(wpbay_get_text_inline( 'It was difficult to set up', 'wpbay-sdk' )),
            'compatibility_issue'=> esc_html(wpbay_get_text_inline( 'Theme or plugin compatibility issues', 'wpbay-sdk' )),
            'performance_concerns' => esc_html(wpbay_get_text_inline( 'It slowed down my site', 'wpbay-sdk' )),
            'poor_support'       => esc_html(wpbay_get_text_inline( 'Unsatisfied with support', 'wpbay-sdk' )),
            'learning_curve'     => esc_html(wpbay_get_text_inline( 'The product was hard to use', 'wpbay-sdk' )),
            'switched_premium'   => esc_html(wpbay_get_text_inline( 'I upgraded to the premium version', 'wpbay-sdk' )),
            'other'              => esc_html(wpbay_get_text_inline( 'Other', 'wpbay-sdk' )),
        );
        $feedback_reasons = apply_filters('wpbay_sdk_feedback_reasons_list', $feedback_reasons);
        return $feedback_reasons;
    }

    public function handle_feedback_submission() {
        check_ajax_referer( 'wpbay_sdk_submit_feedback_nonce', 'nonce' );
        if(!current_user_can( 'edit_posts' ))
        {
            wp_send_json_error( array( 'message' => esc_html(wpbay_get_text_inline( 'You are not allowed to do this action.', 'wpbay-sdk' )) ) );
        }
        $reason       = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
        $details      = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
        $product_slug = isset( $_POST['product_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['product_slug'] ) ) : '';
        $product_id   = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';

        if ( empty( $reason ) || empty( $product_slug ) || empty( $product_id ) ) {
            wp_send_json_error( array( 'message' => esc_html(wpbay_get_text_inline( 'Invalid feedback data.', 'wpbay-sdk' )) ) );
        }

        $purchase_code = $this->license_manager->get_purchase_code();
        if ( empty( $purchase_code ) ) {
            wp_send_json_error( array( 'message' => esc_html(wpbay_get_text_inline( 'License not found.', 'wpbay-sdk' )) ) );
        }

        $api_url = $this->api_endpoint . 'submit';
        $args    = array(
            'body' => array(
                'purchase_code' => $purchase_code,
                'product_slug'  => $product_slug,
                'product_id'    => $product_id,
                'reason'        => $reason,
                'details'       => $details,
                'site_url'      => home_url(),
                'api_key'       => $this->license_manager->get_api_key(),
                'developer_mode'=> $this->license_manager->get_developer_mode(),
                'secret_key'    => $this->license_manager->get_secret_key()
            ),
            'timeout' => 90,
        );

        $response = $this->api_manager->post_request( $api_url, $args );

        if ( ! empty( $response['error'] ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Failed to submit feedback!');
            }
            wp_send_json_error( array( 'message' => esc_html(wpbay_get_text_inline( 'Failed to submit feedback.', 'wpbay-sdk' )) ) );
        }

        wp_send_json_success( array( 'message' => esc_html(wpbay_get_text_inline( 'Feedback submitted successfully.', 'wpbay-sdk' )) ) );
    }
}
