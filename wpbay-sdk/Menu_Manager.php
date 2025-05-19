<?php
namespace WPBaySDK;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPBaySDK\Menu_Manager')) {
    class Menu_Manager
    {
        private static $instances = array();

        private $product_slug = '';
        private $product_basename = '';
        private $action_links = array();
        private $action_links_hooked = false;
        private $product_type = 'plugin';
        private $menu_data = array();
        private $menu_slug = '';
        private $original_menu_slug = '';
        private $activation_url = '#';
        private $is_top_level = false;
        private $no_activation_required = false;
        private $is_free = false;
        private $is_upgradable = false;
        private $disable_support_page = false;
        private $disable_contact_form = false;
        private $disable_upgrade_form = false;
        private $contact_form_manager = null;
        private $purchase_manager = null;
        private $license_manager = null;
        private $product_id = null;
        private $api_key = null;
        private $debug_mode = false;

        private $menu_parent_slug = null;
        private $uploaded_to_wp_org = false;
        private $capability = 'manage_options';

        public static function get_instance($args, $product_slug, $product_basename, $product_type, $uploaded_to_wp_org, $no_activation_required, $license_manager, $contact_form_manager, $purchase_manager, $product_id, $api_key, $debug_mode)
        {
            if (!isset(self::$instances[$product_slug])) {
                self::$instances[$product_slug] = new self($args, $product_slug, $product_basename, $product_type, $uploaded_to_wp_org, $no_activation_required, $license_manager, $contact_form_manager, $purchase_manager, $product_id, $api_key, $debug_mode);
            }
            return self::$instances[$product_slug];
        }

        private function __construct($args, $product_slug, $product_basename, $product_type, $uploaded_to_wp_org, $no_activation_required, $license_manager, $contact_form_manager, $purchase_manager, $product_id, $api_key, $debug_mode)
        {
            $this->product_slug         = $product_slug;
            $this->product_basename     = $product_basename;
            $this->product_type         = $product_type;
            $this->uploaded_to_wp_org   = $uploaded_to_wp_org;
            $this->product_id           = $product_id;
            $this->api_key              = $api_key;
            $this->license_manager      = $license_manager;
            $this->debug_mode           = $debug_mode;

            $this->is_free              = isset($args['is_free']) ? $args['is_free'] : false;
            $this->is_upgradable        = isset($args['is_upgradable']) ? $args['is_upgradable'] : false;
            $this->disable_support_page = isset($args['disable_support_page']) ? $args['disable_support_page'] : false;
            $this->disable_contact_form = isset($args['disable_contact_form']) ? $args['disable_contact_form'] : false;
            $this->disable_upgrade_form = isset($args['disable_upgrade_form']) ? $args['disable_upgrade_form'] : false;

            $this->contact_form_manager = $contact_form_manager;
            $this->purchase_manager     = $purchase_manager;

            if (isset($args['menu_data']) && is_array($args['menu_data'])) 
            {
                $this->menu_data = $args['menu_data'];
                if(isset($this->menu_data['menu_slug']) && !empty($this->menu_data['menu_slug']))
                {
                    $this->menu_slug          = $this->menu_data['menu_slug'];
                    $this->original_menu_slug = $this->menu_data['menu_slug'];
                    if (!isset($this->menu_data['parent']) || !isset($this->menu_data['parent']['menu_slug']))
                    {
                        $this->is_top_level       = true;
                    }
                }
                $this->no_activation_required = $no_activation_required;
                if(!isset($this->menu_data['page_title']) || empty($this->menu_data['page_title']))
                {
                    $product_settings_name = wpbay_sdk_get_product_name($this->product_type, $this->product_slug);
                    if($this->product_type == 'plugin')
                    {
                        if(empty($product_settings_name))
                        {
                            $product_settings_name = 'Plugin Settings';;
                        }
                        $this->menu_data['page_title'] = $product_settings_name;
                    }
                    else
                    {
                        if(empty($product_settings_name))
                        {
                            $product_settings_name = 'Theme Settings';;
                        }
                        $this->menu_data['page_title'] = $product_settings_name;
                    }
                }
                if(!isset($this->menu_data['menu_title']) || empty($this->menu_data['menu_title']))
                {
                    $product_settings_name = wpbay_sdk_get_product_name($this->product_type, $this->product_slug);
                    if($this->product_type == 'plugin')
                    {
                        if(empty($product_settings_name))
                        {
                            $product_settings_name = 'Plugin';;
                        }
                        $this->menu_data['menu_title'] = $product_settings_name;
                    }
                    else
                    {
                        if(empty($product_settings_name))
                        {
                            $product_settings_name = 'Theme';;
                        }
                        $this->menu_data['menu_title'] = $product_settings_name;
                    }
                }
                if(!isset($this->menu_data['capability']) || empty($this->menu_data['capability']))
                {
                    $this->menu_data['capability'] = 'manage_options';
                }
                $this->capability = isset($this->menu_data['capability']) ? $this->menu_data['capability'] : 'manage_options';

                if (isset($this->menu_data['parent'])) 
                {
                    if(isset($this->menu_data['parent']['menu_slug']))
                    {
                        $this->menu_parent_slug = $this->menu_data['parent']['menu_slug'];
                        $this->is_top_level     = ( $this->menu_parent_slug === $this->product_slug );
                    }
                }
            } 
            else 
            {
                $this->set_default_menu_data();
            }
            $this->init_hooks();
            if (!wpbay_sdk_is_ajax()) 
            {
                add_action(( is_multisite() && wpbay_sdk_is_network_admin() ? 'network_' : '' ) . 'admin_menu', array( $this, 'admin_menu_init' ), WPBAY_LOWEST_PRIORITY);
                add_action( 'admin_init', array( &$this, 'add_license_activation' ) );
                add_action( 'admin_init', array( &$this, 'hook_action_links' ), WPBAY_LOWEST_PRIORITY );
            }
        }
        public function add_license_activation()
        {
            if ( !wpbay_sdk_is_user_admin() ) 
            {
                return;
            }
            if ( $this->is_free ) 
            {
                return;
            }
            $purchase_code = $this->license_manager->get_purchase_code();
            if ( empty($purchase_code) ) 
            {
                $this->add_license_action_link();
            }
        }
        private function get_activation_url()
        {
            return $this->activation_url;
        }
        private function add_license_action_link()
        {
            $link_url = $this->get_activation_url();
            if(!empty($link_url) && $link_url != '#')
            {
                $link_text = esc_html(wpbay_get_text_inline('Activate License', 'wpbay-sdk'));
                $link_text = apply_filters( 'wpbay_sdk_menu_activate_license', $link_text );
                $link_text = esc_html($link_text);
                $this->add_plugin_action_link(
                    $link_text,
                    $link_url,
                    false,
                    11,
                    'wpbay-activate-license ' . $this->product_slug
                );
            }
        }
        public function hook_action_links() 
        {
            if ( wpbay_sdk_is_plugins_page() && $this->product_type == 'plugin' ) {
                $this->hook_plugin_action_links();
            }
        }
        private function hook_plugin_action_links() 
        {
            if($this->action_links_hooked === false)
            {
                $this->action_links_hooked = true;
                add_filter( 'plugin_action_links_' . $this->product_basename, array(
                    &$this,
                    'modify_plugin_action_links_hook'
                ), 10, 2 );
                add_filter( 'network_admin_plugin_action_links_' . $this->product_basename, array(
                    &$this,
                    'modify_plugin_action_links_hook'
                ), 10, 2 );
            }
        }
        function modify_plugin_action_links_hook( $links, $file ) 
        {
            $passed_deactivate = false;
            $deactivate_link   = '';
            $before_deactivate = array();
            $after_deactivate  = array();
            foreach ( $links as $key => $link ) 
            {
                if ( 'deactivate' === $key ) 
                {
                    $deactivate_link   = $link;
                    $passed_deactivate = true;
                    continue;
                }
                if ( ! $passed_deactivate ) {
                    $before_deactivate[ $key ] = $link;
                } else {
                    $after_deactivate[ $key ] = $link;
                }
            }
            ksort( $this->action_links );
            foreach ( $this->action_links as $new_links ) {
                foreach ( $new_links as $link ) {
                    $before_deactivate[ $link['key'] ] = '<a href="' . $link['href'] . '"' . ( $link['external'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $link['label'] . '</a>';
                }
            }
            if ( ! empty( $deactivate_link ) ) 
            {
                $before_deactivate['deactivate'] = $deactivate_link;
            }
            return array_merge( $before_deactivate, $after_deactivate );
        }
        public function add_plugin_action_link( $label, $url, $external = false, $priority = 10, $key = false ) 
        {
            if ( ! isset( $this->action_links[ $priority ] ) ) 
            {
                $this->action_links[ $priority ] = array();
            }
            if ( false === $key ) 
            {
                $key = preg_replace( "/[^A-Za-z0-9 ]/", '', strtolower( $label ) );
            }
            $this->action_links[ $priority ][] = array(
                'label'    => $label,
                'href'     => $url,
                'key'      => $key,
                'external' => $external
            );
        }
        private function product_has_menu()
        {
            if(empty($this->original_menu_slug)) 
            {
                return false;
            }
            return true;
        }
        private function override_menu_with_activation() 
        {
            $hook = false;
            $product_settings_name = wpbay_sdk_get_product_name($this->product_type, $this->product_slug);
            if($this->product_type == 'theme')
            {
                if(empty($product_settings_name))
                {
                    $product_settings_name = 'Theme Settings';;
                }
            }
            else
            {
                if(empty($product_settings_name))
                {
                    $product_settings_name = 'Plugin Settings';;
                }
            }
            if($this->product_has_menu() === false)
            {
                $hook = wpbay_sdk_add_page_submenu(
                    '',
                    $product_settings_name,
                    $product_settings_name,
                    'manage_options',
                    $this->product_slug,
                    array( &$this, 'wpbay_sdk_activation_page_render' )
                );
                $this->activation_url = admin_url('admin.php?page=' . $this->product_slug);
            }
            elseif( $this->is_top_level === true ) 
            {
                $hook = $this->override_menu_item( array( &$this, 'wpbay_sdk_activation_page_render' ) );

                if ( false === $hook ) 
                {
                    $hook = wpbay_sdk_add_page_menu(
                        $product_settings_name,
                        $product_settings_name,
                        'manage_options',
                        $this->menu_slug,
                        array( &$this, 'wpbay_sdk_activation_page_render' )
                    );
                }
                $this->activation_url = admin_url('admin.php?page=' . $this->menu_slug);
            }
            else
            {
                $menus = array( $this->menu_parent_slug );
                foreach ( $menus as $parent_slug ) 
                {
                    $hook = $this->override_submenu_action(
                        $parent_slug,
                        $this->menu_slug,
                        array( &$this, 'wpbay_sdk_activation_page_render' )
                    );

                    if ( false !== $hook ) 
                    {
                        $this->activation_url = admin_url($this->menu_parent_slug . '?page=' . $this->menu_slug);
                        break;
                    }
                }
            }
        }
        public function admin_menu_init()
        {
            if (!$this->is_free && !$this->no_activation_required) 
            {
                $purchase_code = $this->license_manager->get_purchase_code();
                if ( empty($purchase_code) ) 
                {
                    $this->override_menu_with_activation();
                }
            }
        }
        private function override_submenu_action( $parent_slug, $menu_slug, $function ) 
        {
			global $submenu;

			$menu_slug   = plugin_basename( $menu_slug );
			$parent_slug = plugin_basename( $parent_slug );

			if ( ! isset( $submenu[ $parent_slug ] ) ) {
				return false;
			}

			$found_submenu_item = false;
			foreach ( $submenu[ $parent_slug ] as $submenu_item ) {
				if ( $menu_slug === $submenu_item[2] ) {
					$found_submenu_item = $submenu_item;
					break;
				}
			}

			if ( false === $found_submenu_item ) {
				return false;
			}

			$hookname = get_plugin_page_hookname( $menu_slug, $parent_slug );
			remove_all_actions( $hookname );

			add_action( $hookname, $function, WPBAY_LOWEST_PRIORITY );

			return $hookname;
		}
        private function is_cpt() 
        {
			return ( 0 === strpos( $this->menu_slug, 'edit.php?post_type=' ) );
		}
        private function get_cpt_slug() 
        {
			if (preg_match('/edit\.php\?post_type=([a-zA-Z0-9_-]+)/', $this->menu_slug, $matches)) 
            {
                return $matches[1];
            }
            return false;
		}
		private function override_menu_item( $function ) 
        {
			$found_menu = $this->remove_menu_item(false);
			if ( false === $found_menu ) {
				return false;
			}

			if ( $this->is_top_level === false || !$this->is_cpt() ) 
            {
				$menu_slug = plugin_basename( $this->menu_slug );

				$hookname = get_plugin_page_hookname( $menu_slug, '' );
				add_action( $hookname, $function, WPBAY_LOWEST_PRIORITY );
			} 
            else 
            {
				global $menu;
                if(is_array($menu) && isset($menu[ $found_menu['position'] ]))
                {
				    unset( $menu[ $found_menu['position'] ] );
                }
                $hookname = wpbay_sdk_add_page_menu(
                    $found_menu['menu'][0],
                    $found_menu['menu'][0],
                    'manage_options',
                    $this->product_slug,
                    $function,
                    $found_menu['menu'][6],
                    $found_menu['position']
                );
			}

			return $hookname;
		}
        private function remove_menu_item( $remove_top_level_menu = false ) {

            $top_level_menu = $this->find_top_level_menu();
            if ( false === $top_level_menu ) {
                return false;
            }
            remove_all_actions( $top_level_menu['hook_name'] );
            $this->remove_all_submenu_items();

            if ( $remove_top_level_menu ) {
                global $menu;
                if(is_array($menu) && isset($menu[ $top_level_menu['position'] ]))
                {
                    unset( $menu[ $top_level_menu['position'] ] );
                }
            }

            return $top_level_menu;
        }
        private function find_top_level_menu() 
        {
			global $menu;
            if(!is_array($menu))
            {
                return false;
            }
			$position   = - 1;
			$found_menu = false;

			$menu_slug = $this->menu_slug;

			$hook_name = get_plugin_page_hookname( $menu_slug, '' );
			foreach ( $menu as $pos => $m ) {
				if ( $menu_slug === $m[2] ) {
					$position   = $pos;
					$found_menu = $m;
					break;
				}
			}

			if ( false === $found_menu ) {
				return false;
			}

			return array(
				'menu'      => $found_menu,
				'position'  => $position,
				'hook_name' => $hook_name
			);
		}
        private function remove_all_submenu_items() {
			global $submenu;

			$menu_slug = $this->menu_slug;

			if ( ! isset( $submenu[ $menu_slug ] ) ) {
				return false;
			}
			$submenu_ref               = &$submenu;
			$submenu_ref[ $menu_slug ] = array();

			return true;
		}
        public function wpbay_sdk_activation_page_render() 
        {
            $args = array('is_free' => $this->is_free, 'purchase_code' => $this->license_manager->get_purchase_code(), 'product_slug' => $this->product_slug);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the activate.php template contains safe escaped and safe code.
            echo wpbay_sdk_apply_filters( 'wpbay_sdk_activation_page', wpbay_sdk_get_template( 'activate.php', $args ), $this->product_slug );
        }

        private function set_default_menu_data()
        {
            $product_settings_name = wpbay_sdk_get_product_name($this->product_type, $this->product_slug);
            if($this->product_type == 'theme')
            {
                if(empty($product_settings_name))
                {
                    $product_settings_name = 'Theme Settings';;
                }
                $this->menu_data = array(
                    'page_title' => $product_settings_name,
                    'menu_title' => $product_settings_name,
                    'capability' => 'manage_options',
                    'menu_slug'  => 'wpbay-settings',
                );
            }
            else
            {
                if(empty($product_settings_name))
                {
                    $product_settings_name = 'Plugin Settings';;
                }
                $this->menu_data = array(
                    'page_title' => $product_settings_name,
                    'menu_title' => $product_settings_name,
                    'capability' => 'manage_options',
                    'menu_slug'  => 'wpbay-settings',
                );
            }
            $this->menu_slug = $this->menu_data['menu_slug'];
        }

        private function init_hooks()
        {
            if (!$this->is_free && !$this->no_activation_required) 
            {
                $purchase_code = $this->license_manager->get_purchase_code();
                if ( empty($purchase_code) ) 
                {
                    return;
                }
            }
            add_action('admin_menu', array($this, 'add_admin_menus'), WPBAY_LOWEST_PRIORITY - 1);
            if (is_multisite()) {
                add_action('network_admin_menu', array($this, 'add_network_admin_menus'), WPBAY_LOWEST_PRIORITY - 1);
            }
        }

        public function add_admin_menus()
        {
            if (!current_user_can($this->capability)) 
            {
                return;
            }
            if($this->all_submenus_disabled())
            {
                return;
            }
            if(empty($this->menu_slug))
            {
                return;
            }

            if ($this->product_has_submenu() || $this->product_is_submenu()) 
            {
                $parent_slug = $this->menu_slug;
            } 
            else 
            {
                wpbay_sdk_add_page_menu(
                    $this->menu_data['page_title'],
                    $this->menu_data['menu_title'],
                    $this->capability,
                    $this->menu_slug,
                    array($this, 'render_main_page'),
                    isset($this->menu_data['icon_url']) ? $this->menu_data['icon_url'] : '',
                    isset($this->menu_data['position']) ? $this->menu_data['position'] : null
                );
                $parent_slug = $this->menu_slug;
            }
            if(!empty($parent_slug) || $this->product_is_submenu())
            {
                $this->add_sdk_submenus($parent_slug);
            }
        }

        public function add_network_admin_menus()
        {
            if (!is_network_admin()) 
            {
                return;
            }
            if($this->all_submenus_disabled())
            {
                return;
            }
            if (!current_user_can($this->capability)) 
            {
                return;
            }
            if ($this->product_has_network_menu() || $this->product_is_submenu()) {
                $parent_slug = $this->menu_slug;
            } else {
                wpbay_sdk_add_page_menu(
                    $this->menu_data['page_title'],
                    $this->menu_data['menu_title'],
                    $this->capability,
                    $this->menu_slug,
                    array($this, 'render_main_page'),
                    isset($this->menu_data['icon_url']) ? $this->menu_data['icon_url'] : '',
                    isset($this->menu_data['position']) ? $this->menu_data['position'] : null
                );
                $parent_slug = $this->menu_slug;
            }
            if(!empty($parent_slug) || $this->product_is_submenu())
            {
                $this->add_sdk_submenus($parent_slug, true);
            }
        }

        private function product_is_submenu()
        {
            if(!empty($this->menu_parent_slug))
            {
                return true;
            }
            return false;
        }

        private function product_has_submenu()
        {
            global $menu, $submenu;
            if(empty($this->menu_slug))
            {
                return false;
            }
            if(!is_array($menu))
            {
                return false;
            }
            foreach ($menu as $menu_item) 
            {
                if ($menu_item[2] === $this->menu_slug) 
                {
                    $this->is_top_level = true;
                    return true;
                }
            }
            if(!is_array($submenu))
            {
                return false;
            }
            foreach ($submenu as $parent_slug => $submenu_items) 
            {
                foreach ($submenu_items as $submenu_item) 
                {
                    if ($submenu_item[2] === $this->menu_slug) 
                    {
                        $this->is_top_level = false;
                        return true;
                    }
                }
            }

            return false;
        }

        private function product_has_network_menu()
        {
            global $menu, $submenu;
            if(empty($this->menu_slug))
            {
                return false;
            }
            foreach ($menu as $menu_item) 
            {
                if ($menu_item[2] === $this->menu_slug) 
                {
                    $this->is_top_level = true;
                    return true;
                }
            }

            foreach ($submenu as $parent_slug => $submenu_items) 
            {
                foreach ($submenu_items as $submenu_item) 
                {
                    if ($submenu_item[2] === $this->menu_slug) 
                    {
                        $this->is_top_level = false;
                        return true;
                    }
                }
            }

            return false;
        }

        private function all_submenus_disabled()
        {
            if((!$this->is_free || !$this->is_upgradable || $this->purchase_manager === null || $this->disable_upgrade_form) && ($this->disable_contact_form || $this->contact_form_manager === null) && ($this->disable_support_page || !$this->uploaded_to_wp_org) && (!$this->is_free || !$this->is_upgradable || $this->purchase_manager === null))
            {
                return true;
            }
            return false;
        }

        private function add_sdk_submenus($parent_slug, $is_network = false)
        {
            $capability = $this->capability;
            if ($is_network) 
            {
                $capability = 'manage_network_options';
            }
            if(!empty($this->menu_parent_slug))
            {
                $parent_slug = $this->menu_parent_slug;
            }
            if(!empty($this->menu_slug))
            {
                $menu_slug = $this->menu_slug;
            }
            else
            {
                $menu_slug = 'wp';
            }
            if (!$this->disable_contact_form && $this->contact_form_manager !== null) 
            {
                $content_text = esc_html(wpbay_get_text_inline('Contact', 'wpbay-sdk'));
                $content_text = apply_filters( 'wpbay_sdk_menu_contact', $content_text );
                $content_text = esc_html($content_text);
                wpbay_sdk_add_page_submenu(
                    $parent_slug,
                    $content_text,
                    $content_text,
                    $capability,
                    $menu_slug . '-contact',
                    array($this, 'render_contact_page')
                );
            }
            if (!$this->disable_support_page && $this->uploaded_to_wp_org) 
            {
                $support_text = esc_html(wpbay_get_text_inline('Support', 'wpbay-sdk'));
                $support_text = apply_filters( 'wpbay_sdk_menu_support', $support_text );
                $support_text = esc_html($support_text);
                wpbay_sdk_add_page_submenu(
                    $parent_slug,
                    $support_text,
                    $support_text,
                    $capability,
                    $menu_slug . '-support',
                    array($this, 'render_support_page')
                );
            }
            if ($this->disable_upgrade_form !== true && $this->is_free && $this->is_upgradable && !empty($this->product_id) && !empty($this->api_key) && $this->purchase_manager !== null) 
            {
                global $wpbay_sdk_version;
                wp_enqueue_style(
                    'wpbay-purchase-manager-style',
                    plugin_dir_url( __FILE__ ) . 'styles/purchase.css',
                    array(),
                    $wpbay_sdk_version
                );
                $upgrade_text = esc_html(wpbay_get_text_inline('Upgrade', 'wpbay-sdk'));
                $upgrade_text = apply_filters( 'wpbay_sdk_menu_upgrade', $upgrade_text );
                $upgrade_text = esc_html($upgrade_text);
                wpbay_sdk_add_page_submenu(
                    $parent_slug,
                    $upgrade_text,
                    $upgrade_text,
                    $capability,
                    $menu_slug . '-upgrade',
                    array($this, 'render_upgrade_page')
                );
            }
        }

        public function get_wp_support_forum_url()
        {
            return 'https://wordpress.org/support/' . $this->product_type . '/' . $this->product_slug;
        }

        public function render_main_page()
        {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html($this->menu_data['page_title']) . '</h1>';
            $welcome_text = esc_html(wpbay_get_text_inline('Welcome to the main settings page of', 'wpbay-sdk'));
            $welcome_text = apply_filters( 'wpbay_sdk_menu_contact', $welcome_text );
            echo '<p>' . esc_html($welcome_text) . '&nbsp;<b>' . esc_html($this->menu_data['page_title']). '</b></p>';
            echo '</div>';
        }

        public function render_contact_page()
        {
            if($this->contact_form_manager !== null)
            {
                $this->contact_form_manager->contact_form_field_callback();
            }
        }

        public function render_support_page()
        {
            $support_url = $this->get_wp_support_forum_url();
            wp_redirect($support_url);
            exit;
        }

        public function render_upgrade_page()
        {
            if($this->purchase_manager !== null)
            {
                $this->purchase_manager->upgrade_options_field_callback();
            }
        }
    }
}
?>