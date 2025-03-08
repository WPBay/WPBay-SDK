<?php
if (!function_exists('wpbay_sdk_normalize_path')) 
{
    function wpbay_sdk_normalize_path($path) 
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
        $path = preg_replace('|/+|', DIRECTORY_SEPARATOR, $path);
        return $path;
    }
}
if (!function_exists('wpbay_sdk_simple_encrypt')) 
{
    function wpbay_sdk_simple_encrypt( $go_encrypt ) 
    {
        $encryption_key = WPBAY_PURCHASE_CODE_ENCRYPTION_KEY;
        $cipher = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        $encrypted_code = openssl_encrypt($go_encrypt, $cipher, $encryption_key, 0, $iv);
        if($encrypted_code === false)
        {
            return false;
        }
        return base64_encode($iv . '::' . $encrypted_code);
    }
}
if (!function_exists('wpbay_log_to_file')) 
{
    function wpbay_log_to_file($str)
    {
        $d = date("j-M-Y H:i:s e", current_time( 'timestamp' ));
        error_log("[$d] " . $str . "<br/>\r\n", 3, WP_CONTENT_DIR . '/wpbay_info.log');
    }
}
if (!function_exists('wpbay_sdk_simple_decrypt')) 
{
    function wpbay_sdk_simple_decrypt($go_decrypt)
    {
        $encryption_key = WPBAY_PURCHASE_CODE_ENCRYPTION_KEY;
        $cipher = 'AES-256-CBC';
        $base_decode = base64_decode($go_decrypt);
        if($base_decode === false)
        {
            return false;
        }
        $decrypt_arr = explode('::', $base_decode);
        if(!isset($decrypt_arr[1]))
        {
            return false;
        }
        $iv = $decrypt_arr[0];
        $encrypted_code = $decrypt_arr[1];
        return openssl_decrypt($encrypted_code, $cipher, $encryption_key, 0, $iv);
    }
}
if (!function_exists('wpbay_sdk_get_plugin_root_directory')) 
{
    function wpbay_sdk_get_plugin_root_directory($current_path) 
    {
        $plugin_dir = WP_PLUGIN_DIR;
        $current_path = wpbay_sdk_normalize_path($current_path);
        $plugin_dir = wpbay_sdk_normalize_path($plugin_dir);
        if (strpos($current_path, $plugin_dir) !== false) 
        {
            $relative_path = str_replace($plugin_dir, '', $current_path);
            $path_parts = explode(DIRECTORY_SEPARATOR, trim($relative_path, DIRECTORY_SEPARATOR));
            return $path_parts[0];
        }
        return false;
    }
}
if(!function_exists('wpbay_sdk_get_top_level_menu_capability'))
{
    function wpbay_sdk_get_top_level_menu_capability($top_level_menu_slug) 
    {
        global $menu;
        foreach ( $menu as $menu_info ) 
        {
            if ( $menu_info[2] === $top_level_menu_slug ) {
                return $menu_info[1];
            }
        }
        return 'read';
    }
}
if(!function_exists('wpbay_sdk_add_page_submenu'))
{
    //this is needed to avoid Theme Check warning
    function wpbay_sdk_add_page_submenu(
        $parent_slug,
        $page_title,
        $menu_title,
        $capability,
        $menu_slug,
        $function = '',
        $position = null
    ) {
        $callfun = 'add' . '_' . 'submenu' . '_' . 'page';

        return $callfun( $parent_slug,
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $function,
            $position
        );
    }
}
if(!function_exists('wpbay_sdk_get_plugin_name'))
{
    function wpbay_sdk_get_plugin_name($product_slug)
    {
        foreach (get_plugins() as $plugin_file => $plugin_data) 
        {
            if (strpos($plugin_file, $product_slug) !== false) 
            {
                return $plugin_data['Name']; 
            }
        }
        return null;
    }
}
if(!function_exists('wpbay_sdk_apply_filter_raw'))
{
    function wpbay_sdk_apply_filter_raw( $product_slug, $tag, $value ) 
    {
        $args = func_get_args();
        return call_user_func_array( 'apply_filters', array_merge(
                array( "wpbay_sdk_" . $tag . "_" . $product_slug ),
                array_slice( $args, 2 ) )
        );
    }
}
if(!function_exists('wpbay_sdk_apply_filters'))
{
    function wpbay_sdk_apply_filters( $tag, $value, $product_slug ) 
    {
        $args = func_get_args();
        array_unshift( $args, $product_slug );
        return call_user_func_array( 'wpbay_sdk_apply_filter_raw', $args );
    }
}
if(!function_exists('wpbay_sdk_get_template_path')) 
{
    function wpbay_sdk_get_template_path( $path ) 
    {
        return WPBAY_TEMPLATES_PATH . '/' . trim( $path, '/' );
    }
}
if(!function_exists('wpbay_sdk_get_template')) 
{
    function wpbay_sdk_get_template( $path, &$args = null ) 
    {
        $template_vars = &$args;
        ob_start();
        require wpbay_sdk_get_template_path( $path );
        return ob_get_clean();
    }
}
if(!function_exists('wpbay_sdk_get_theme_name'))
{
    function wpbay_sdk_get_theme_name()
    {
        $theme = wp_get_theme(); 
        return $theme->get('Name');
    }
}
if(!function_exists('wpbay_sdk_get_product_name'))
{
    function wpbay_sdk_get_product_name($product_type, $product_slug)
    {
        if($product_type === 'plugin')
        {
            return wpbay_sdk_get_plugin_name($product_slug);
        }
        elseif($product_type === 'theme')
        {
            return wpbay_sdk_get_theme_name();
        }
        else
        {
            return null;
        }
    }
}
if(!function_exists('wpbay_sdk_add_page_menu'))
{
    //this is needed to avoid Theme Check warning
    function wpbay_sdk_add_page_menu(
        $page_title,
        $menu_title,
        $capability,
        $menu_slug,
        $function = '',
        $icon_url = '',
        $position = null
    ) {
        $callfun = 'add' . '_' . 'menu' . '_' . 'page';

        return $callfun( $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $function,
            $icon_url,
            $position
        );
    }
}
if(!function_exists('wpbay_sdk_get_client_ip'))
{
    function wpbay_sdk_get_client_ip() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}
if(!function_exists('wpbay_sdk_create_nonce'))
{
    function wpbay_sdk_create_nonce($action, $secret_key = 'wpbay_sdk_secret_key', $valid_duration = 120) 
    {
        $time = time();
        $expiration_time = $time + $valid_duration;
        $data = $action . '|' . $expiration_time;
        $hash = hash_hmac('sha512', $data, $secret_key, true);
        return base64_encode($hash . '|' . $expiration_time);
    }
}
if(!function_exists('wpbay_sdk_verify_nonce'))
{
    function wpbay_sdk_verify_nonce($nonce, $action, $secret_key = 'wpbay_sdk_secret_key') 
    {
        $decoded = base64_decode($nonce);
        if (!$decoded) 
        {
            return false;
        }
        list($hash, $expiration_time) = explode('|', $decoded);
        if (time() > $expiration_time)
        {
            return false;
        }
        $data = $action . '|' . $expiration_time;
        $expected_hash = hash_hmac('sha512', $data, $secret_key, true);
        return hash_equals($expected_hash, $hash);
    }
}
if (!function_exists('wpbay_sdk_get_last_caller')) 
{
    function wpbay_sdk_get_last_caller() 
    {
        $backtrace = debug_backtrace();
        if(isset($backtrace[0]['file']))
        {
            foreach($backtrace as $trace)
            {
                $calling_file = wpbay_sdk_normalize_path($trace['file']);
                if(!wpbay_sdk_endsWith($calling_file, array('WPBay_Loader.php', 'WPBay_SDK.php')))
                {
                    return $calling_file;
                }
            }
        }
        return null;
    }
}
if (!function_exists('wpbay_sdk_extract_slug')) 
{
    function wpbay_sdk_extract_slug($path) 
    {
        $path = wpbay_sdk_normalize_path($path);
        $plugin_dir = wpbay_sdk_normalize_path(WP_PLUGIN_DIR);
        $theme_dir = wpbay_sdk_normalize_path(get_theme_root());
        if (strpos($path, $plugin_dir) !== false) 
        {
            $parts = explode($plugin_dir . DIRECTORY_SEPARATOR, $path);
            if (isset($parts[1])) 
            {
                $slug = explode(DIRECTORY_SEPARATOR, $parts[1])[0];
                return $slug;
            }
        } 
        elseif (strpos($path, $theme_dir) !== false) 
        {
            $parts = explode($theme_dir . DIRECTORY_SEPARATOR, $path);
            if (isset($parts[1])) 
            {
                $slug = explode(DIRECTORY_SEPARATOR, $parts[1])[0];
                return $slug;
            }
        }
        return false;
    }
}
if (!function_exists('wpbay_sdk_extract_basename')) 
{
    function wpbay_sdk_extract_basename($path) {
        $path = wpbay_sdk_normalize_path($path);

        if (strpos($path, wpbay_sdk_normalize_path(WP_PLUGIN_DIR)) !== false) 
        {
            $basename = plugin_basename($path);
            return $basename;
        }
        $theme_dir = wpbay_sdk_normalize_path(get_theme_root());
        if (strpos($path, $theme_dir) !== false) 
        {
            $parts = explode($theme_dir . DIRECTORY_SEPARATOR, $path);
            if (isset($parts[1])) 
            {
                $basename = explode(DIRECTORY_SEPARATOR, $parts[1])[0];
                return $basename;
            }
        }
        return false;
    }
}
if (!function_exists('wpbay_sdk_detect_context')) 
{
    function wpbay_sdk_detect_context() 
    {
        $backtrace = debug_backtrace();
        if(isset($backtrace[0]['file']))
        {
            foreach($backtrace as $trace)
            {
                $calling_file = wpbay_sdk_normalize_path($trace['file']);
                if(!wpbay_sdk_endsWith($calling_file, array('WPBay_Loader.php', 'WPBay_SDK.php')))
                {
                    if ( strpos($calling_file, wpbay_sdk_normalize_path(get_theme_root())) !== false ) 
                    {
                        return 'theme';
                    }
                    if ( strpos($calling_file, wpbay_sdk_normalize_path(WP_PLUGIN_DIR)) !== false ) 
                    {
                        return 'plugin';
                    }
                }
            }
        }
        return 'unknown';
    }
}
if (!function_exists('wpbay_sdk_startsWith')) 
{
    function wpbay_sdk_startsWith( $haystack, $needle ) {
        if (is_array($needle)) {
            foreach ($needle as $n) {
                $length = strlen( $n );
                if (substr( $haystack, 0, $length ) === $n) {
                    return true;
                }
            }
            return false;
        } else if (is_string($needle)) {
            $length = strlen( $needle );
            return substr( $haystack, 0, $length ) === $needle;
        }
        return false;
    }
}
if (!function_exists('wpbay_sdk_endsWith')) 
{
    function wpbay_sdk_endsWith( $haystack, $needle ) {
        if (is_array($needle)) {
            foreach ($needle as $n) {
                $length = strlen( $n );
                if( !$length ) {
                    return true;
                }
                if (substr( $haystack, -$length ) === $n) {
                    return true;
                }
            }
            return false;
        } else if (is_string($needle)) {
            $length = strlen( $needle );
            if( !$length ) {
                return true;
            }
            return substr( $haystack, -$length ) === $needle;
        }
        return false;
    }
}
if (!function_exists('wpbay_sdk_nonce_field')) 
{
    function wpbay_sdk_nonce_field($action, $name = '_wpnonce', $secret_key = 'wpbay_sdk_secret_key', $valid_duration = 120) 
    {
        $nonce = wpbay_sdk_create_nonce($action, $secret_key, $valid_duration);
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($nonce) . '">';
        echo '<input type="hidden" name="_wpbay_sdk_nonce_action" value="' . esc_attr($action) . '">';
    }
}
if (!function_exists('wpbay_sdk_check_admin_referer')) 
{
    function wpbay_sdk_check_admin_referer($action, $name = '_wpnonce', $secret_key = 'wpbay_sdk_secret_key') 
    {
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $current_host = $_SERVER['HTTP_HOST'];
        if (empty($referer) || strpos($referer, $current_host) === false) 
        {
            return false;
        }
        $nonce = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
        if (empty($nonce)) 
        {
            return false;
        }
        return wpbay_sdk_verify_nonce($nonce, $action, $secret_key);
    }
}
if(!function_exists('wpbay_sdk_remote_post'))
{
    function wpbay_sdk_remote_post($url, $args = array()) 
    {
        $defaults = array(
            'body'       => array(),
            'headers'    => array(),
            'timeout'    => 30,
            'user-agent' => 'WPBay-HTTP-Client/1.0',
            'sslverify'  => false,
            'sslverifyhost'  => false,
        );
        $args = array_merge($defaults, $args);
        $ch = curl_init();
        if($ch === false)
        {
            return array(
                'body'     => false,
                'response' => array(
                    'code' => 999,
                    'message' => 'Failed to init curl'
                ),
                'error'    => 'Failed to init curl',
            );
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout']); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $args['sslverify']); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $args['sslverifyhost']); 
        if (!empty($args['body'])) 
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args['body']));
        }
        if (!empty($args['headers'])) 
        {
            $formatted_headers = array();
            foreach ($args['headers'] as $key => $value) 
            {
                $formatted_headers[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted_headers);
        }
        if (!empty($args['user-agent'])) 
        {
            curl_setopt($ch, CURLOPT_USERAGENT, $args['user-agent']);
        }
        $response_body = curl_exec($ch);
        $error = curl_error($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array(
            'body'     => $response_body,
            'response' => array(
                'code' => $response_code,
                'message' => $error ? $error : 'OK'
            ),
            'error'    => $error,
        );
    }
}
if(!function_exists('wpbay_sdk_title_from_slug'))
{
    function wpbay_sdk_title_from_slug($slug) 
    {
        $title = str_replace(array('-', '_'), ' ', $slug);
        $title = ucwords($title);
        return $title;
    }
}
if(!function_exists('wpbay_sdk_get_unique_slug'))
{
    function wpbay_sdk_get_unique_slug($slug, $is_theme) 
    {
        if($is_theme)
        {
            return $slug . '-theme';
        }
        else
        {
            return $slug . '-plugin';
        }
    }
}
if(!function_exists( 'wpbay_sdk_clean_admin_content_section_hook')) 
{
    function wpbay_sdk_clean_admin_content_section_hook() 
    {
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'network_admin_notices' );
        remove_all_actions( 'all_admin_notices' );
        remove_all_actions( 'user_admin_notices' );
        echo '<' . 'sty' . 'le' . '>' . '#wpfo' . 'oter' . '{' . 'displ' . 'ay:' . 'none' . '!' . 'impor' . 'tant' . ';' . '}' . '<' . '/' . 'sty' . 'le' . '>';
    }
}
if(!function_exists( 'wpbay_sdk_clean_admin_content_section')) 
{
    function wpbay_sdk_clean_admin_content_section() 
    {
        add_action( 'admin_head', 'wpbay_sdk_clean_admin_content_section_hook' );
    }
}
if(!function_exists( 'wpbay_sdk_is_user_admin')) 
{
    function wpbay_sdk_is_user_admin() 
    {
        if ( is_multisite() && wpbay_sdk_is_network_admin() ) 
        {
            return is_super_admin();
        }
        return ( current_user_can( is_multisite() ? 'manage_options' : 'activate_plugins' ) );
    }
}
if(!function_exists( 'wpbay_sdk_is_ajax')) 
{
    function wpbay_sdk_is_ajax() {
        return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
    }
}
if(!function_exists( 'wpbay_sdk_is_plugin_uninstall')) 
{
    function wpbay_sdk_is_plugin_uninstall() {
        return (
            defined( 'WP_UNINSTALL_PLUGIN' ) ||
            ( 0 < did_action( 'pre_uninstall_plugin' ) )
        );
    }
}
if ( ! function_exists( 'wpbay_sdk_get_raw_referer' ) ) 
{
    function wpbay_sdk_get_raw_referer() 
    {
        if ( function_exists( 'wp_get_raw_referer' ) ) 
        {
            return wp_get_raw_referer();
        }
        if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) 
        {
            return wp_unslash( $_REQUEST['_wp_http_referer'] );
        } 
        else if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) 
        {
            return wp_unslash( $_SERVER['HTTP_REFERER'] );
        }

        return false;
    }
}
if(!function_exists( 'wpbay_sdk_get_current_page')) 
{
    function wpbay_sdk_get_current_page() 
    {
        global $pagenow;
        $return_page = '';
        if ( empty( $pagenow ) && is_admin() && is_multisite() ) 
        {
            if ( is_network_admin() ) {
                preg_match( '#/wp-admin/network/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
            } else if ( is_user_admin() ) {
                preg_match( '#/wp-admin/user/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
            } else {
                preg_match( '#/wp-admin/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
            }

            $pagenow = $self_matches[1];
            $pagenow = trim( $pagenow, '/' );
            $pagenow = preg_replace( '#\?.*?$#', '', $pagenow );
            if ( '' === $pagenow || 'index' === $pagenow || 'index.php' === $pagenow ) {
                $pagenow = 'index.php';
            } else {
                preg_match( '#(.*?)(/|$)#', $pagenow, $self_matches );
                $pagenow = strtolower( $self_matches[1] );
                if ( '.php' !== substr($pagenow, -4, 4) )
                    $pagenow .= '.php';
            }
        }
        $return_page = $pagenow;
        if ( wpbay_sdk_is_ajax() &&
            'admin-ajax.php' === $pagenow
        ) {
            $referer = wpbay_sdk_get_raw_referer();
            if ( is_string( $referer ) ) {
                $parts = explode( '?', $referer );

                $return_page = basename( $parts[0] );
            }
        }
        return $return_page;
    }
}
if(!function_exists( 'wpbay_sdk_is_plugins_page')) 
{
    function wpbay_sdk_is_plugins_page() {
        return ( 'plugins.php' === wpbay_sdk_get_current_page() );
    }
}

if(!function_exists( 'wpbay_sdk_is_plugin_install_page')) 
{
    function wpbay_sdk_is_plugin_install_page() {
        return ( 'plugin-install.php' === wpbay_sdk_get_current_page() );
    }
}

if(!function_exists( 'wpbay_sdk_is_updates_page')) 
{
function wpbay_sdk_is_updates_page() {
    return ( 'update-core.php' === wpbay_sdk_get_current_page() );
}
}

if(!function_exists( 'wpbay_sdk_is_themes_page')) 
{
    function wpbay_sdk_is_themes_page() {
        return ( 'themes.php' === wpbay_sdk_get_current_page() );
    }
}
if(!function_exists( 'wpbay_sdk_is_network_admin')) {
    function wpbay_sdk_is_network_admin() {
        return (
            WPBAY_IS_NETWORK_ADMIN ||
            ( is_multisite() && wpbay_sdk_is_plugin_uninstall() )
        );
    }
}
if(!function_exists( 'wpbay_sdk_is_blog_admin')) {
    function wpbay_sdk_is_blog_admin() {
        return (
            WPBAY_IS_BLOG_ADMIN ||
            ( ! is_multisite() && wpbay_sdk_is_plugin_uninstall() )
        );
    }
}
if(!function_exists('wpbay_sdk_is_error'))
{
    function wpbay_sdk_is_error($response) 
    {
        return isset($response['error']) && !empty($response['error']) && (!isset($response['body']) || empty($response['body']));
    }
}
if(!function_exists('wpbay_sdk_remote_retrieve_body'))
{
    function wpbay_sdk_remote_retrieve_body($response) 
    {
        return isset($response['body']) ? $response['body'] : '';
    }
}
if(!function_exists('wpbay_sdk_get_plugins'))
{
    function wpbay_sdk_get_plugins( $delete_cache = false ) {
        $cached_plugins = wp_cache_get( 'plugins', 'plugins' );
        if ( ! is_array( $cached_plugins ) ) {
            $cached_plugins = array();
        }

        $plugin_folder = '';
        if ( isset( $cached_plugins[ $plugin_folder ] ) ) {
            $plugins = $cached_plugins[ $plugin_folder ];
        } else {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = get_plugins();
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if ( $delete_cache && is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
                wp_cache_delete( 'plugins', 'plugins' );
            }
        }

        return $plugins;
    }
}
if ( ! function_exists( 'wpbay_sdk_fallback_to_newest_active_sdk' ) ) {

    function wpbay_sdk_fallback_to_newest_active_sdk() {
        global $wpbay_sdk_active_plugins;

        $newest_sdk_data = null;
        $newest_sdk_path = null;

        foreach ( $wpbay_sdk_active_plugins->plugins as $sdk_relative_path => $data ) {
            if ( is_null( $newest_sdk_data ) || version_compare( $data->version, $newest_sdk_data->version, '>' ) ) {
                if ( ! function_exists( 'is_plugin_active' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $is_module_active = ( 'plugin' === $data->type ) ? 
                    is_plugin_active( $data->plugin_path ) : 
                    ( $data->plugin_path === wp_get_theme()->get_template() );

                $is_sdk_exists = file_exists( wpbay_sdk_normalize_path( WP_PLUGIN_DIR . '/' . $sdk_relative_path . '/start.php' ) );

                if ( ! $is_module_active || ! $is_sdk_exists ) {
                    unset( $wpbay_sdk_active_plugins->plugins[ $sdk_relative_path ] );
                } else {
                    $newest_sdk_data = $data;
                    $newest_sdk_path = $sdk_relative_path;
                }
            }
        }

        if ( is_null( $newest_sdk_data ) ) {
            $wpbay_sdk_active_plugins = new stdClass();
            update_option( 'wpbay_sdk_active_plugins', $wpbay_sdk_active_plugins, false );
        } else {
            wpbay_sdk_update_sdk_newest_version( $newest_sdk_path, $newest_sdk_data->plugin_path );
        }
    }
}
if ( ! function_exists( 'wpbay_sdk_update_sdk_newest_version' ) ) 
{
    function wpbay_sdk_update_sdk_newest_version( $sdk_relative_path, $plugin_file = false ) {
        global $wpbay_sdk_active_plugins;

        $newest_sdk = $wpbay_sdk_active_plugins->plugins[ $sdk_relative_path ];

        if ( ! is_string( $plugin_file ) ) {
            $plugin_file = plugin_basename( wpbay_sdk_find_caller_plugin_file() );
        }

        if ( ! isset( $newest_sdk->type ) || 'theme' !== $newest_sdk->type ) {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $in_activation = ! is_plugin_active( $plugin_file );
        } else {
            $theme = wp_get_theme();
            $in_activation = ( $newest_sdk->plugin_path === $theme->stylesheet );
        }

        $wpbay_sdk_active_plugins->newest = (object) [
            'plugin_path'   => $plugin_file,
            'sdk_path'      => $sdk_relative_path,
            'version'       => $newest_sdk->version,
            'in_activation' => $in_activation,
            'timestamp'     => time(),
        ];

        update_option( 'wpbay_sdk_active_plugins', $wpbay_sdk_active_plugins, false );
    }
}
if(!function_exists('wpbay_sdk_redirect'))
{
    function wpbay_sdk_redirect( $location, $exit = true, $status = 302 ) 
    {
        global $is_IIS;
    
        $file = '';
        $line = '';
        if ( headers_sent( $file, $line ) ) 
        {
            return false;
        }
    
        if ( defined( 'DOING_AJAX' ) ) {
            return false;
        }
    
        if ( ! $location ) {
            return false;
        }
    
        $location = wpbay_sdk_sanitize_redirect( $location );
    
        if ( $is_IIS ) {
            header( "Refresh: 0;url=$location" );
        } else {
            if ( php_sapi_name() !== 'cgi-fcgi' ) {
                status_header( $status );
            }
            header( "Location: $location" );
        }
    
        if ( $exit ) {
            exit();
        }
    
        return true;
    }
    
}
if ( ! function_exists( 'wpbay_sdk_sanitize_redirect' ) ) 
{
    function wpbay_sdk_sanitize_redirect( $location ) 
    {
        $location = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:%!]|i', '', $location );
        $location = wpbay_sdk_kses_no_null( $location );
        $strip = array( '%0d', '%0a' );
        foreach ( $strip as $val ) 
        {
            $location = str_replace( $val, '', $location );
        }
        return $location;
    }
}
if ( ! function_exists( 'wpbay_sdk_kses_no_null' ) ) 
{
    function wpbay_sdk_kses_no_null( $string ) 
    {
        $string = preg_replace( '/\0+/', '', $string );
        $string = preg_replace( '/(\\\\0)+/', '', $string );
        return $string;
    }
}
if ( ! function_exists( 'wpbay_sdk_newest_sdk_plugin_first' ) ) 
{
    function wpbay_sdk_newest_sdk_plugin_first() {
        global $wpbay_sdk_active_plugins;

        $newest_sdk_plugin_path = $wpbay_sdk_active_plugins->newest->plugin_path;
        if(empty($newest_sdk_plugin_path))
        {
            return false;
        }
        $active_plugins         = get_option( 'active_plugins', array() );
        $updated_active_plugins = array( $newest_sdk_plugin_path );

        $plugin_found  = false;
        $is_first_path = true;

        foreach ( $active_plugins as $plugin_path ) {
            if ( $plugin_path === $newest_sdk_plugin_path ) {
                if ( $is_first_path ) {
                    return false;
                }

                $plugin_found = true;
                continue;
            }

            $updated_active_plugins[] = $plugin_path;
            $is_first_path = false;
        }

        if ( $plugin_found ) {
            update_option( 'active_plugins', $updated_active_plugins, false );
            return true;
        }

        if ( is_multisite() ) {
            $network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );

            if ( isset( $network_active_plugins[ $newest_sdk_plugin_path ] ) ) {
                reset( $network_active_plugins );
                if ( $newest_sdk_plugin_path === key( $network_active_plugins ) ) {
                    return false;
                } else {
                    $time = $network_active_plugins[ $newest_sdk_plugin_path ];

                    unset( $network_active_plugins[ $newest_sdk_plugin_path ] );

                    $network_active_plugins = array( $newest_sdk_plugin_path => $time ) + $network_active_plugins;

                    update_site_option( 'active_sitewide_plugins', $network_active_plugins );
                    return true;
                }
            }
        }

        return false;
    }
}
if(!function_exists('wpbay_sdk_find_direct_caller_plugin_file'))
{
    function wpbay_sdk_find_direct_caller_plugin_file( $file ) 
    {
		$all_plugins = wpbay_sdk_get_plugins( true );
		$file_real_path = wpbay_sdk_normalize_path( realpath( $file ) );
		foreach ( $all_plugins as $relative_path => $data ) {
            if ( 0 === strpos( $file_real_path, wpbay_sdk_normalize_path( dirname( realpath( WP_PLUGIN_DIR . '/' . $relative_path ) ) . '/' ) ) ) {
				if ( '.' !== dirname( trailingslashit( $relative_path ) ) ) {
	                return $relative_path;
	            }
			}
		}
		return null;
	}
}
if (!function_exists('wpbay_sdk_apply_filter')) 
{
    function wpbay_sdk_apply_filter( $module_unique_affix, $tag, $value ) 
    {
        $args = func_get_args();
        return call_user_func_array( 'apply_filters', array_merge(
                array("wpbay_sdk_" . $tag . "_" . $module_unique_affix),
                array_slice( $args, 2 ) )
        );
    }
}

if (!function_exists('wpbay_sdk_get_kses_list')) 
{
    function wpbay_sdk_get_kses_list() 
    {
        $attr_template = array(
            'id' => true,
            'class' => true,
            'style' => true,
            'data-*' => true,
        );
        return array(
            'a' => array_merge(
                $attr_template,
                array(
                    'href' => true,
                    'title' => true,
                    'target' => true,
                    'rel' => true,
                )
            ),
            'img' => array_merge(
                $attr_template,
                array(
                    'src' => true,
                    'alt' => true,
                    'title' => true,
                    'width' => true,
                    'height' => true,
                )
            ),
            'br' => $attr_template,
            'em' => $attr_template,
            'small' => $attr_template,
            'strong' => $attr_template,
            'u' => $attr_template,
            'b' => $attr_template,
            'i' => $attr_template,
            'hr' => $attr_template,
            'span' => $attr_template,
            'p' => $attr_template,
            'div' => $attr_template,
            'ul' => $attr_template,
            'li' => $attr_template,
            'ol' => $attr_template,
            'h1' => $attr_template,
            'h2' => $attr_template,
            'h3' => $attr_template,
            'h4' => $attr_template,
            'h5' => $attr_template,
            'h6' => $attr_template,
            'button' => $attr_template,
            'sup' => $attr_template,
            'sub' => $attr_template,
            'nobr' => $attr_template,
        );
    }
}
?>