<?php
namespace WPBaySDK;

if (!defined('ABSPATH')) {
    exit;
}

class Contact_Form_Manager 
{
    private static $instances = array();
    private $product_slug;
    private $api_manager;
    private $wpbay_sdk_api_endpoint;
    private $license_manager;
    private $debug_mode;
    private $no_activation_required;
    private $is_free;
    private $product_id;
    private static $initialized = false;

    private function __construct($product_slug, $api_manager, $license_manager, $is_free, $no_activation_required, $product_id, $debug_mode) {
        $this->product_slug = $product_slug;
        $this->api_manager = $api_manager;
        $this->debug_mode = $debug_mode;
        $this->license_manager = $license_manager;
        $this->no_activation_required = $no_activation_required;
        $this->is_free = $is_free;
        $this->product_id = $product_id;
        // API endpoint for submitting contact form data (name, email, request type, etc.) to WPBay.com.
        // For full data usage details, refer to WPBay_Loader.php or the SDK readme file.
        $this->wpbay_sdk_api_endpoint = 'https://wpbay.com/api/contact/v1/submit';

        add_action('admin_init', array($this, 'register_contact_form_settings_field'));

        add_action('wp_ajax_wpbay_sdk_send_contact_message_' . sanitize_title($this->product_slug), array($this, 'handle_form_submission'));
    }

    public static function get_instance($product_slug, $api_manager, $license_manager, $is_free, $no_activation_required, $product_id, $debug_mode) {
        if (!isset(self::$instances[$product_slug])) {
            self::$instances[$product_slug] = new self($product_slug, $api_manager, $license_manager, $is_free, $no_activation_required, $product_id, $debug_mode);
        }
        return self::$instances[$product_slug];
    }
    public function render_contact_form() {
        $this->contact_form_field_callback();
    }
    public function register_contact_form_settings_field() 
    {
        if ( ! self::$initialized ) 
        {
            global $wpbay_sdk_version;
            wp_enqueue_style(
                'wpbay-contact-manager-style',
                plugin_dir_url( __FILE__ ) . 'styles/contact.css',
                array(),
                $wpbay_sdk_version
            );
            self::$initialized = true;
        }
        if($this->license_manager->is_developer_mode() === true)
        {
            $section_id = 'wpbay_sdk_contact_section_' . $this->product_slug;
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
                esc_html(wpbay_get_text_inline('Get in Touch with the Author', 'wpbay-sdk')),
                null,
                $settings_name
            );

            add_settings_field(
                'wpbay_sdk_contact_form_' . $this->product_slug,
                esc_html(wpbay_get_text_inline('Contact Form', 'wpbay-sdk')),
                array($this, 'contact_form_field_callback'),
                $settings_name,
                $section_id
            );
        }
    }

    public function contact_form_field_callback() {
        if(empty($this->product_id))
        {
            echo 'To use this feature, you need to add a valid product ID in the WPBay SDK parameters.';
            return;
        }
        $current_user = wp_get_current_user();
        $user_email = !empty($current_user->user_email) ? $current_user->user_email : '';
        $first_name = !empty($current_user->first_name) ? $current_user->first_name : '';
        $last_name = !empty($current_user->last_name) ? $current_user->last_name : '';
        $nonce = wp_create_nonce('wpbay_sdk_send_contact_message_' . $this->product_slug);
        ?>

        <div class="wpbay-sdk-contact-form-wrapper" id="wpbay-contact-form-<?php echo esc_attr($this->product_slug); ?>">
            <table class="form-table">
            <tr>
            <th scope="row">
                <label for="wpbay-contact-first-name-<?php echo esc_attr($this->product_slug); ?>">
                    <?php $first_name_text = esc_html(wpbay_get_text_inline('First Name:', 'wpbay-sdk'));
                    $first_name_text = apply_filters('wpbay_sdk_contact_form_text_first_name', $first_name_text);
                    echo esc_html($first_name_text); ?>
                </label>
                </th>
            <td>
                <input type="text" id="wpbay-contact-first-name-<?php echo esc_attr($this->product_slug); ?>" class="wpbay-contact-input" name="wpbay_sdk_contact_first_name" value="<?php echo esc_attr($first_name); ?>" placeholder="<?php echo esc_html(wpbay_get_text_inline('Your first name', 'wpbay-sdk'));?>" required>
            </td>
            </tr>
            <tr>
            <th scope="row">
                <label for="wpbay-contact-last-name-<?php echo esc_attr($this->product_slug); ?>">
                <?php $last_name_text = esc_html(wpbay_get_text_inline('Last Name:', 'wpbay-sdk'));
                    $last_name_text = apply_filters('wpbay_sdk_contact_form_text_last_name', $last_name_text);
                    echo esc_html($last_name_text); ?>
                </label>
            </th>
            <td>
                <input type="text" id="wpbay-contact-last-name-<?php echo esc_attr($this->product_slug); ?>" class="wpbay-contact-input" name="wpbay_sdk_contact_last_name" value="<?php echo esc_attr($last_name); ?>" placeholder="<?php echo esc_html(wpbay_get_text_inline('Your last name', 'wpbay-sdk'));?>" required>
            </td>
            </tr>
            <tr>
            <th scope="row">
                <label for="wpbay-contact-email-<?php echo esc_attr($this->product_slug); ?>">
                <?php $email_address_text = esc_html(wpbay_get_text_inline('Email Address:', 'wpbay-sdk'));
                    $email_address_text = apply_filters('wpbay_sdk_contact_form_text_last_name', $email_address_text);
                    echo esc_html($email_address_text); ?>
                </label>
            </th>
            <td>
                <input type="email" id="wpbay-contact-email-<?php echo esc_attr($this->product_slug); ?>" class="wpbay-contact-input" name="wpbay_sdk_contact_email" value="<?php echo esc_attr($user_email); ?>" placeholder="<?php echo esc_html(wpbay_get_text_inline('Your contact email', 'wpbay-sdk'));?>" required>
            </td>
            </tr>
            <tr>
            <th scope="row">
                <label for="wpbay-contact-request-type-<?php echo esc_attr($this->product_slug); ?>">
                <?php $type_text = esc_html(wpbay_get_text_inline('Type of Request:', 'wpbay-sdk'));
                    $type_text = apply_filters('wpbay_sdk_contact_form_text_type_of_request', $type_text);
                    echo esc_html($type_text); ?>
                </label>
            </th>
            <td>
                <select id="wpbay-contact-request-type-<?php echo esc_attr($this->product_slug); ?>" class="wpbay-contact-input" name="wpbay_sdk_contact_request_type" required>
                    <option value="pre_sale_question"><?php echo esc_html(wpbay_get_text_inline('Pre-Sale Question', 'wpbay-sdk')); ?></option>
<?php
$purchase_code = 'no_need';
if (!$this->is_free && !$this->no_activation_required) 
{
    $purchase_code = $this->license_manager->get_purchase_code();
}
if ( !empty($purchase_code) ) 
{
?>
                    <option value="technical_support"><?php echo esc_html(wpbay_get_text_inline('Technical Support', 'wpbay-sdk')); ?></option>
                    <option value="billing_issue"><?php echo esc_html(wpbay_get_text_inline('Billing Inquiry', 'wpbay-sdk')); ?></option>
<?php
}
?>
                    <option value="feature_request"><?php echo esc_html(wpbay_get_text_inline('Feature Suggestion', 'wpbay-sdk')); ?></option>
                    <option value="customization"><?php echo esc_html(wpbay_get_text_inline('Customization Request', 'wpbay-sdk')); ?></option>
                    <option value="press"><?php echo esc_html(wpbay_get_text_inline('Press Inquiry', 'wpbay-sdk')); ?></option>
                </select>
            </td>
            </tr>
            <tr>
            <th scope="row">
                <label for="wpbay-contact-summary-<?php echo esc_attr($this->product_slug); ?>">
                <?php $subject_text = esc_html(wpbay_get_text_inline('Subject:', 'wpbay-sdk'));
                    $subject_text = apply_filters('wpbay_sdk_contact_form_text_subject', $subject_text);
                    echo esc_html($subject_text); ?>
                </label>
            </th>
            <td>
                <input type="text" id="wpbay-contact-summary-<?php echo esc_attr($this->product_slug); ?>" class="wpbay-contact-input" name="wpbay_sdk_contact_summary" maxlength="100" required placeholder="<?php echo esc_html(wpbay_get_text_inline('Your title', 'wpbay-sdk'));?>">
                </td>
            </tr>
            <tr>
            <th scope="row">
                <label for="wpbay-contact-message-<?php echo esc_attr($this->product_slug); ?>">
                <?php $description_text = esc_html(wpbay_get_text_inline('Detailed Description:', 'wpbay-sdk'));
                    $description_text = apply_filters('wpbay_sdk_contact_form_text_description', $description_text);
                    echo esc_html($description_text); ?>
                </label>
            </th>
            <td>
                <textarea id="wpbay-contact-message-<?php echo esc_attr($this->product_slug); ?>" class="wpbay-contact-input" name="wpbay_sdk_contact_message" rows="5" cols="50" placeholder="<?php echo esc_html(wpbay_get_text_inline('Your detailed description', 'wpbay-sdk'));?>" required></textarea>
                </td>
            </tr>
            </table>
            <p class="submit">
            <button type="button" id="wpbay-send-contact-message-<?php echo esc_attr($this->product_slug); ?>" class="button button-primary">
            <?php $submit_text = esc_html(wpbay_get_text_inline('Submit', 'wpbay-sdk'));
                    $submit_text = apply_filters('wpbay_sdk_contact_form_button_submit', $submit_text);
                    echo esc_html($submit_text); ?>
            </button>&nbsp;&nbsp;&nbsp;
            <span id="wpbay-contact-form-status-<?php echo esc_attr($this->product_slug); ?>"></span>
            </p>
        </div>
<?php
$submit_id = 'wpbay-send-contact-message-' . esc_js( $this->product_slug );
$status_id = 'wpbay-contact-form-status-' . esc_js( $this->product_slug );
$form_id   = 'wpbay-contact-form-' . esc_js( $this->product_slug );
$nonce_js  = esc_js( $nonce );
$action    = 'wpbay_sdk_send_contact_message_' . esc_js( $this->product_slug );

$sending_text = esc_js( apply_filters( 'wpbay_sdk_contact_form_label_sending', esc_html( wpbay_get_text_inline( 'Sending...', 'wpbay-sdk' ) ) ) );
$sent_text    = esc_js( apply_filters( 'wpbay_sdk_contact_form_label_sent', esc_html( wpbay_get_text_inline( 'Your message has been sent successfully!', 'wpbay-sdk' ) ) ) );
$error_text   = esc_js( apply_filters( 'wpbay_sdk_contact_form_label_error', esc_html( wpbay_get_text_inline( 'An error occurred. Please try again.', 'wpbay-sdk' ) ) ) );

$inline_js = "
jQuery(document).ready(function($) {
    $('#$submit_id').on('click', function(e) {
        e.preventDefault();
        var firstName = $('#wpbay-contact-first-name-{$this->product_slug}').val();
        var lastName = $('#wpbay-contact-last-name-{$this->product_slug}').val();
        var email = $('#wpbay-contact-email-{$this->product_slug}').val();
        var requestType = $('#wpbay-contact-request-type-{$this->product_slug}').val();
        var summary = $('#wpbay-contact-summary-{$this->product_slug}').val();
        var message = $('#wpbay-contact-message-{$this->product_slug}').val();
        var nonce = '$nonce_js';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: '$action',
                first_name: firstName,
                last_name: lastName,
                email: email,
                request_type: requestType,
                summary: summary,
                message: message,
                nonce: nonce
            },
            beforeSend: function() {
                $('#$status_id').html('$sending_text');
            },
            success: function(response) {
                if (response.success) {
                    $('#$status_id').html('$sent_text');
                    $('#$form_id')[0].reset();
                } else {
                    $('#$status_id').html(response.data);
                }
            },
            error: function() {
                $('#$status_id').html('$error_text');
            }
        });
    });
});
";
        wp_add_inline_script( 'wpbay-sdk-contact', $inline_js );
    }

    public function handle_form_submission() {
        check_ajax_referer('wpbay_sdk_send_contact_message_' . $this->product_slug, 'nonce');
        if(!current_user_can( 'edit_posts' ))
        {
            return;
        }
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $request_type = isset($_POST['request_type']) ? sanitize_text_field(wp_unslash($_POST['request_type'])) : '';
        $summary = isset($_POST['summary']) ? sanitize_text_field(wp_unslash($_POST['summary'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        if (empty($first_name) || empty($last_name) || empty($email) || empty($request_type) || empty($summary) || empty($message)) {
            wp_send_json_error(esc_html(wpbay_get_text_inline('All fields are required.', 'wpbay-sdk')));
        }
        if(empty($this->product_id))
        {
            wp_send_json_error(esc_html(wpbay_get_text_inline('To use this feature, you need to add a valid product ID in the WPBay SDK parameters.', 'wpbay-sdk')));
            return;
        }
        $response = $this->send_contact_message_to_api($first_name, $last_name, $email, $request_type, $summary, $message);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success();
        }
    }

    private function send_contact_message_to_api($first_name, $last_name, $email, $request_type, $summary, $message) {
        $api_url = $this->wpbay_sdk_api_endpoint;

        $args = array(
            'body' => array(
                'product_slug' => $this->product_slug,
                'product_id'   => $this->product_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'email'        => $email,
                'request_type' => $request_type,
                'summary'      => $summary,
                'message'      => $message,
                'api_key'      => $this->license_manager->get_api_key()
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 90,
        );

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Failed to submit contact form request: ' . $response->get_error_message());
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code != 200) {
            return new \WP_Error('api_error', esc_html(wpbay_get_text_inline('Failed to send message: ', 'wpbay-sdk')) . $code);
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['success']) && $result['success']) {
            return true;
        } else {
            $error_message = isset($result['message']) ? $result['message'] : esc_html(wpbay_get_text_inline('An unknown error occurred.', 'wpbay-sdk'));
            return new \WP_Error('api_error', $error_message);
        }
    }
}
?>
