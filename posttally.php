<?php
/*
 * Plugin Name: PostTally
 * Plugin URI: https://wordpress.org/plugins/posttally/
 * Description: PostTally displays the total number of published posts using a shortcode (use [post_count] anywhere in your content). It also integrates optional licensing, upgrade paths, and analytics via the WPBay SDK to support developers who want more features in the future.
 * Version: 1.0.0
 * Author: CaddyTzitzy
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: posttally
 */

/**
 * Prevent direct access to this file.
 */
if (!defined('ABSPATH')) {
    exit;
}

if ( ! function_exists( 'posttally_load_wpbay_sdk' ) ) {
    function posttally_load_wpbay_sdk() {
        require_once dirname( __FILE__ ) . '/wpbay-sdk/WPBay_Loader.php';
        $sdk_instance = false;
        global $wpbay_sdk_latest_loader;
        $sdk_loader_class = $wpbay_sdk_latest_loader;
        $sdk_params = array(
            'api_key'                 => '5REUOK-AE3ZCTMHAIZWBTZBOQ26HJNPKA',
            'wpbay_product_id'        => '', 
            'product_file'            => __FILE__,
            'activation_redirect'     => '',
            'is_free'                 => true,
            'is_upgradable'           => false,
            'uploaded_to_wp_org'      => true,
            'disable_feedback'        => false,
            'disable_support_page'    => false,
            'disable_contact_form'    => false,
            'disable_upgrade_form'    => false,
            'disable_analytics'       => false,
            'rating_notice'           => '1 week',
            'debug_mode'              => 'false',
            'no_activation_required'  => true,
            'menu_data'               => array(
                'menu_slug' => ''
            ),
        );
        if ( class_exists( $sdk_loader_class ) ) {
            $sdk_instance = $sdk_loader_class::load_sdk( $sdk_params );
        }
        return $sdk_instance;
    }
    posttally_load_wpbay_sdk();
    do_action( 'posttally_load_wpbay_sdk_loaded' );
}
/**
 * Class to handle the PostTally functionality.
 */
class PostTally {

    /**
     * Constructor to initialize the plugin.
     */
    public function __construct() {
        // Register the shortcode
        add_action('init', array($this, 'register_shortcodes'));
    }

    /**
     * Register the [post_count] shortcode.
     */
    public function register_shortcodes() {
        add_shortcode('post_count', array($this, 'post_count_shortcode'));
    }

    /**
     * Shortcode callback to display the total number of published posts.
     *
     * @param array $atts Shortcode attributes (not used here).
     * @return string The total post count.
     */
    public function post_count_shortcode($atts) {
        // Get the count of published posts
        $post_count = wp_count_posts('post')->publish;

        // Sanitize and return the output
        return esc_html(number_format_i18n($post_count));
    }
}

/**
 * Instantiate the plugin class.
 */
new PostTally();