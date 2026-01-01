<?php
/**
 * Admin Panel
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CheckoutKeys_Admin {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = CheckoutKeys_Database::get_instance();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('CheckoutKeys', 'checkoutkeys'),
            __('CheckoutKeys', 'checkoutkeys'),
            'manage_options',
            'checkoutkeys',
            array($this, 'render_dashboard'),
            'dashicons-admin-network',
            56
        );
        
        add_submenu_page(
            'checkoutkeys',
            __('License Keys', 'checkoutkeys'),
            __('License Keys', 'checkoutkeys'),
            'manage_options',
            'checkoutkeys',
            array($this, 'render_dashboard')
        );
        
        add_submenu_page(
            'checkoutkeys',
            __('Settings', 'checkoutkeys'),
            __('Settings', 'checkoutkeys'),
            'manage_options',
            'checkoutkeys-settings',
            array($this, 'render_settings')
        );
        
        add_submenu_page(
            'checkoutkeys',
            __('Logs', 'checkoutkeys'),
            __('Logs', 'checkoutkeys'),
            'manage_options',
            'checkoutkeys-debug',
            array($this, 'render_debug')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'checkoutkeys') === false) {
            return;
        }
        
        // Ensure dashicons are loaded
        wp_enqueue_style('dashicons');
        
        wp_enqueue_style(
            'checkoutkeys-admin',
            CHECKOUTKEYS_PLUGIN_URL . 'assets/css/admin.css',
            array('dashicons'),
            CHECKOUTKEYS_VERSION
        );
        
        wp_enqueue_script(
            'checkoutkeys-admin',
            CHECKOUTKEYS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CHECKOUTKEYS_VERSION,
            true
        );
    }
    
    public function render_dashboard() {
        $licenses = $this->db->get_all_licenses(20, 0);
        include CHECKOUTKEYS_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    public function render_settings() {
        include CHECKOUTKEYS_PLUGIN_DIR . 'admin/settings.php';
    }
    
    public function render_debug() {
        require_once CHECKOUTKEYS_PLUGIN_DIR . 'admin/debug.php';
    }}
