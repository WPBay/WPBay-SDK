<?php
namespace WPBaySDK;

if (! defined('ABSPATH')) {
    exit;
}

class Purchase_Manager 
{
    private static $instances = array();

    // Base URL for redirecting users to WPBay's checkout page.
    // See WPBay_Loader.php or the SDK readme file for more details.
    private $wpbay_sdk_endpoint_checkout = 'https://wpbay.com/';
    // API endpoint for fetching available upgrade options and validating upgrade purchases.
    // Full data disclosure is available in WPBay_Loader.php and the SDK readme.
    private $api_upgrade_endpoint = 'https://wpbay.com/api/upgrade/v1/';
    private $product_slug;
    private $wpbay_product_id;
    private $api_key;
    private $api_manager;
    private $license_manager;
    private $debug_mode;
    private static $initialized = false;

    private function __construct($product_slug, $wpbay_product_id, $api_key, $api_manager, $license_manager, $debug_mode) 
    {
        $this->product_slug = $product_slug;
        $this->wpbay_product_id = $wpbay_product_id;
        $this->api_key = $api_key;
        $this->api_manager = $api_manager;
        $this->license_manager = $license_manager;
        $this->debug_mode = $debug_mode;

        add_action('admin_init', array($this, 'register_upgrade_settings_field'));
    }

    public static function get_instance($product_slug, $wpbay_product_id, $api_key, $api_manager, $license_manager, $debug_mode) 
    {
        if (!isset(self::$instances[$product_slug])) {
            self::$instances[$product_slug] = new self($product_slug, $wpbay_product_id, $api_key, $api_manager, $license_manager, $debug_mode);
        }
        return self::$instances[$product_slug];
    }
    public function render_upgrade_form() {
        $this->upgrade_options_field_callback();
    }

    public function register_upgrade_settings_field() 
    {
        if ( ! self::$initialized ) 
        {
            global $wpbay_sdk_version;
            wp_enqueue_style(
                'wpbay-purchase-manager-style',
                plugin_dir_url( __FILE__ ) . 'styles/purchase.css',
                array(),
                $wpbay_sdk_version
            );
            self::$initialized = true;
        }
        if($this->license_manager->is_developer_mode() === true)
        {
            $section_id = 'wpbay_sdk_purchase_section_' . $this->product_slug;
            if (is_multisite())
            {
                $settings_name = 'wpbay-network-settings';
            }
            else
            {
                $settings_name = 'wpbay-settings';
            }
            $register_text = esc_html(wpbay_get_text_inline('Register your purchase code', 'wpbay-sdk'));
            $register_text = apply_filters( 'wpbay_sdk_purchase_message_register', $register_text );
            $register_text = esc_html($register_text);
            add_settings_section(
                $section_id,
                $register_text,
                null,
                $settings_name
            );
            $upgrade_text = esc_html(wpbay_get_text_inline('Upgrade Options', 'wpbay-sdk'));
            $upgrade_text = apply_filters( 'wpbay_sdk_purchase_message_register', $upgrade_text );
            $upgrade_text = esc_html($upgrade_text);
            add_settings_field(
                'wpbay_sdk_upgrade_options' . $this->product_slug,
                $upgrade_text . '<br/>' . $this->product_slug,
                array($this, 'upgrade_options_field_callback'),
                $settings_name,
                $section_id
            );
        }
    }

    public function upgrade_options_field_callback() 
    {
        $upgrades = $this->fetch_upgrades();
        if ($upgrades && (!isset($upgrades['status']) || $upgrades['status'] != '404')) 
        {
            echo '<div id="wpbay-upgrade-options" class="wpbay-upgrade-wrapper">';
            echo '<h3>' . esc_html(wpbay_get_text_inline('Upgrade to Unlock More Features', 'wpbay-sdk')) . '</h3>';
            echo '<p class="more-descriptions">' . esc_html(wpbay_get_text_inline('Choose a license that suits your needs and enjoy premium features, priority support, and updates.', 'wpbay-sdk')) . '</p>';
            echo '<div class="wpbay-upgrade-cards">';
            usort($upgrades, function($a, $b) {
                if ($a['license_count'] == 0 || $a['license_count'] == 'd') {
                    return 1;
                } elseif ($b['license_count'] == 0 || $b['license_count'] == 'd') {
                    return -1;
                } else {
                    return $a['license_count'] <=> $b['license_count'];
                }
            });
            foreach ($upgrades as $option) {
                echo '<div class="wpbay-upgrade-card">';
                echo '<input type="radio" id="upgrade_option_' . esc_attr($option['id']) . '" name="upgrade_option' . esc_html($this->product_slug) . '" value="' . esc_attr($option['id']) . '">';
                echo '<label for="upgrade_option_' . esc_attr($option['id']) . '">';
                echo '<div class="wpbay-upgrade-icon">';
                if ($option['license_count'] == 0) {
                    $option['license_count'] = esc_html(wpbay_get_text_inline('Unlimited', 'wpbay-sdk'));
                    echo '<span class="dashicons dashicons-admin-multisite"></span>';
                }
                elseif ($option['license_count'] == 'd') {
                    $option['license_count'] = esc_html(wpbay_get_text_inline('Developer', 'wpbay-sdk'));
                    echo '<span class="dashicons dashicons-admin-multisite"></span>';
                }
                elseif ($option['license_count'] == 1) {
                    echo '<span class="dashicons dashicons-admin-users"></span>';
                } elseif ($option['license_count'] <= 5) {
                    echo '<span class="dashicons dashicons-groups"></span>';
                } elseif ($option['license_count'] <= 10) {
                    echo '<span class="dashicons dashicons-admin-home"></span>';
                } else {
                    echo '<span class="dashicons dashicons-admin-multisite"></span>';
                }
                echo '</div>';
                echo '<div class="wpbay-upgrade-details">';
                echo '<strong>' . esc_html($option['license_count']) . ' ' . esc_html(wpbay_get_text_inline('Site License', 'wpbay-sdk')) . '</strong>';
                echo '<p class="wpbay-upgrade-price">' . esc_html($option['display_price']) . '</p>';
                echo '</div>';

                echo '</label>';
                echo '</div>';
            }
            echo '</div>';
            $purchase_text = esc_html(wpbay_get_text_inline('Upgrade Now', 'wpbay-sdk'));
            $purchase_text = apply_filters( 'wpbay_sdk_purchase_message_register', $purchase_text );
            echo '<button type="button" id="wpbay-purchase-upgrade' . esc_html($this->product_slug) . '" class="button button-primary wpbay-upgrade-button">' . esc_html($purchase_text) . '</button>';

            $user_email = '';
            $first_name = '';
            $last_name = '';
            if (is_user_logged_in()) 
            {
                $current_user = wp_get_current_user();
                if ($current_user instanceof WP_User) 
                {
                    $user_email = !empty($current_user->user_email) ? $current_user->user_email : '';
                    $first_name = !empty($current_user->first_name) ? $current_user->first_name : '';
                    $last_name = !empty($current_user->last_name) ? $current_user->last_name : '';
                }
            }
            $checkout_url = $this->wpbay_sdk_endpoint_checkout . '?wpbay_checkout=1&add_to_cart=1&user_email=' . urlencode($user_email) . '&user_firstname=' . urlencode($first_name) . '&user_lastname=' . urlencode($last_name) . '&nonce=' . wp_create_nonce('wpbay_sdk_add_to_cart');
            $inline_js = "
jQuery(document).ready(function($) {
    $('.wpbay-upgrade-card').on('click', function() {
        $('.wpbay-upgrade-card').removeClass('selected'); 
        $(this).addClass('selected'); 
        $(this).find('input[type=\"radio\"]').prop('checked', true); 
    });
    $('#wpbay-purchase-upgrade" . esc_js( $this->product_slug ) . "').on('click', function(event) {
        event.preventDefault();
        let upgrade_id = $('input[name=\"upgrade_option" . esc_js( $this->product_slug ) . "\"]:checked').val();
        let checkout_url = '" . esc_js( $checkout_url ) . "';
        if (upgrade_id) {
            checkout_url += '&product_id=' + upgrade_id;
            window.location.replace(checkout_url);
        } else {
            alert('Please select a license plan before proceeding.');
        }
    });
});
";
            wp_add_inline_script( 'wpbay-sdk-upgrade-handler', $inline_js );
            wpbay_sdk_clean_admin_content_section();
        } else {
            $no_options_text = esc_html(wpbay_get_text_inline('No upgrade options available at the moment.', 'wpbay-sdk'));
            $no_options_text = apply_filters( 'wpbay_sdk_purchase_message_no_options', $no_options_text );
            echo '<p>' . esc_html($no_options_text) . '</p>';
        }
    }

    private function fetch_upgrades() 
    {
        if(empty($this->api_upgrade_endpoint) || empty($this->wpbay_product_id) || empty($this->api_key))
        {
            return null;
        }
        $response = $this->api_manager->post_request($this->api_upgrade_endpoint . 'list', [
            'body' => [
                'product_id'    => $this->wpbay_product_id,
                'api_key'       => $this->api_key,
                'site_url'      => home_url(),
            ],
            'timeout' => 90,
        ], true);

        if ( wpbay_sdk_is_error( $response ) ) {
            if($this->debug_mode === true)
            {
                wpbay_log_to_file('Failed to fetch upgrades!');
            }
            return null;
        }

        $response_body = wpbay_sdk_remote_retrieve_body( $response );
        if(empty($response_body))
        {
            return null;
        }
        $result = json_decode( $response_body, true );

        return ($result && isset($result['data'])) ? $result['data'] : null;
    }
}
