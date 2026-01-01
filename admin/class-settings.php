<?php
/**
 * Settings
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CheckoutKeys_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_checkoutkeys_validate_api_key', array($this, 'ajax_validate_api_key'));
    }
    
    public function ajax_validate_api_key() {
        check_ajax_referer('checkoutkeys_validate_key', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required'));
        }
        
        $response = wp_remote_get('https://checkoutkeys.com/api/licenses', array(
            'headers' => array(
                'x-api-key' => $api_key,
            ),
            'timeout' => 10,
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection error'));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['licenses'])) {
                wp_send_json_success(array('valid' => true));
            }
        }
        
        wp_send_json_error(array('message' => 'Invalid API key'));
    }
    
    public function register_settings() {
        register_setting('checkoutkeys', 'checkoutkeys_api_key');
        register_setting('checkoutkeys', 'checkoutkeys_api_url');
        register_setting('checkoutkeys', 'checkoutkeys_debug_mode');
    }
}
