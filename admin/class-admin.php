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
        add_action('wp_ajax_checkoutkeys_toggle_license', array($this, 'ajax_toggle_license'));
        add_action('admin_notices', array($this, 'hide_other_notices'), 1);
    }
    
    /**
     * Hide other plugin notices on CheckoutKeys admin pages
     */
    public function hide_other_notices() {
        // Only hide notices on our plugin pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'checkoutkeys') !== false) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            
            // Re-add our own notices hook if we need it later
            add_action('admin_notices', array($this, 'show_checkoutkeys_notices'));
        }
    }
    
    /**
     * Show only CheckoutKeys-specific notices
     */
    public function show_checkoutkeys_notices() {
        // Reserved for future CheckoutKeys-specific notices
    }
    
    public function ajax_toggle_license() {
        check_ajax_referer('checkoutkeys_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        $action = sanitize_text_field($_POST['toggle_action']);
        $api_key = get_option('checkoutkeys_api_key');
        $api_url = 'https://checkoutkeys.com/api';
        
        if (empty($api_key)) {
            error_log('CheckoutKeys Toggle - API key not configured');
            wp_send_json_error(array('message' => 'API key not configured'));
        }
        
        if (!in_array($action, ['activate', 'deactivate'])) {
            error_log('CheckoutKeys Toggle - Invalid action: ' . $action);
            wp_send_json_error(array('message' => 'Invalid action'));
        }
        
        error_log('CheckoutKeys Toggle - License: ' . $license_key . ' | Action: ' . $action);
        
        $response = wp_remote_post($api_url . '/licensekeys/activateDeactivate', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
            ),
            'body' => json_encode(array(
                'licenseKey' => $license_key,
                'action' => $action,
            )),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('CheckoutKeys Toggle - Error: ' . $error_message);
            wp_send_json_error(array('message' => 'Connection error: ' . $error_message));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('CheckoutKeys Toggle - Status Code: ' . $status_code);
        error_log('CheckoutKeys Toggle - Response: ' . $body);
        
        if ($status_code === 200) {
            $new_status = ($action === 'activate') ? 'active' : 'inactive';
            $this->db->update_license($license_key, array('status' => $new_status));
            
            $action_word = ($action === 'activate') ? 'activated' : 'deactivated';
            error_log('CheckoutKeys Toggle - Success: License ' . $action_word);
            
            wp_send_json_success(array(
                'message' => sprintf('License key successfully %s', $action_word),
                'new_status' => $new_status,
                'new_action' => ($action === 'activate') ? 'deactivate' : 'activate',
                'new_button_text' => ($action === 'activate') ? 'Deactivate' : 'Activate',
                'new_button_class' => ($action === 'activate') ? 'deactivate-btn' : 'activate-btn'
            ));
        } else {
            error_log('CheckoutKeys Toggle - Failed with status: ' . $status_code);
            wp_send_json_error(array('message' => 'Failed to update license status'));
        }
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
