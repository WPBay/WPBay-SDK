<?php
if (!defined('ABSPATH')) {
    exit;
}
if ($template_vars['is_free'] === false) 
{
    $purchase_code = $template_vars['purchase_code'];
    $product_slug = $template_vars['product_slug'];
    wp_enqueue_script( 'wpbay-admin-code-manager', plugins_url( '/../scripts/purchase-code-manager.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
    wp_localize_script( 'wpbay-admin-code-manager', 'wpbay_sdk_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    wp_enqueue_style(
        'wpbay-license-manager-style',
        plugin_dir_url( __FILE__ ) . '../styles/register.css',
        array()
    );
    if ( $purchase_code ) 
    {
        echo '<div class="wpbay-sdk-register-form">';
        echo '<p class="description">' . esc_html__( 'Your purchase code is registered.', 'wpbay-sdk' ) . '</p>';
        echo '<form method="post" class="wpbay-sdk-form">';
        echo '<table class="form-table">';
        echo '<tr><td colspan="2"><input type="button" class="button wpbay-purchase-code-revoke" data-id="' . esc_attr($product_slug). '" value="' . esc_html__( 'Revoke Code', 'wpbay-sdk' ) . '">&nbsp;
        <input type="button" class="button wpbay-purchase-code-registered" data-id="' . esc_attr($product_slug). '" value="' . esc_html__( 'Code Is Registered?', 'wpbay-sdk' ) . '">&nbsp;
        <input type="button" class="button wpbay-purchase-code-check" data-id="' . esc_attr($product_slug). '" value="' . esc_html__( 'Validate Code', 'wpbay-sdk' ) . '">
        <input type="hidden" id="wpbay_sdk_purchase_code' . esc_attr($product_slug). '" value="' . esc_attr( $purchase_code ) . '">
        <input type="hidden" id="wpbay_sdk_security' . esc_attr($product_slug). '" value="' . wp_create_nonce('wpbay_sdk_purchase_code_security') . '"></td></tr></table>';
        echo '</form>';
        echo '</div>';
    } 
    else 
    {
        echo '<div class="wpbay-sdk-register-form">';
        echo '<p class="description">' . esc_html__( 'Register your product by entering your WPBay.com purchase code below.', 'wpbay-sdk' ) . '</p>';
        echo '<form method="post" class="wpbay-sdk-form">';
        echo '<table class="form-table"><tr><th scope="row">';
echo '<label for="wpbay_sdk_purchase_code' . esc_attr($product_slug) . '">' . esc_html__( 'Purchase Code:', 'wpbay-sdk' ) . '</label>';
echo '</th><td>';
echo '<input type="text" id="wpbay_sdk_purchase_code' . esc_attr($product_slug) . '" class="regular-text" placeholder="e.g., ABCDEF-1234567890AB-CDEFGHIJKLMN12-3456789" required>';
echo '<input type="hidden" id="wpbay_sdk_security' . esc_attr($product_slug). '" value="' . wp_create_nonce('wpbay_sdk_purchase_code_security') . '">';
echo '</td></tr></table>';
        echo '<p><input type="button" data-id="' . esc_attr($product_slug). '" class="button button-primary wpbay-purchase-code-register" value="' . esc_html__( 'Register', 'wpbay-sdk' ) . '"></p>';
        echo '</form>';
        echo '</div>';
    }
}
else
{
    echo '<p>' . esc_html__( 'This product is free to use.', 'wpbay-sdk' ) . '</p>';
}
?>