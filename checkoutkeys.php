<?php
/**
 * Plugin Name: License Keys Manager
 * Plugin URI: https://checkoutkeys.com
 * Description: WordPress integration plugin for checkoutkeys.com, license keys management service. Automatically generate and manage license keys for your Stripe Checkout payments.
 * Version: 1.0.0
 * Author: checkoutkeys.com
 * Author URI: https://checkoutkeys.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: checkoutkeys
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CHECKOUTKEYS_VERSION', '1.0.0');
define('CHECKOUTKEYS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHECKOUTKEYS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHECKOUTKEYS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main CheckoutKeys class
 */
class CheckoutKeys {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once CHECKOUTKEYS_PLUGIN_DIR . 'includes/class-database.php';
        
        if (is_admin()) {
            require_once CHECKOUTKEYS_PLUGIN_DIR . 'admin/class-admin.php';
            require_once CHECKOUTKEYS_PLUGIN_DIR . 'admin/class-settings.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize database
        CheckoutKeys_Database::get_instance();
        
        if (is_admin()) {
            CheckoutKeys_Admin::get_instance();
            CheckoutKeys_Settings::get_instance();
        }
        
        // Load text domain
        load_plugin_textdomain('checkoutkeys', false, dirname(CHECKOUTKEYS_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        require_once CHECKOUTKEYS_PLUGIN_DIR . 'includes/class-database.php';
        CheckoutKeys_Database::create_tables();
        
        // Set default options
        add_option('checkoutkeys_version', CHECKOUTKEYS_VERSION);
        add_option('checkoutkeys_api_key', '');
        add_option('checkoutkeys_api_url', 'https://checkoutkeys.com/api');
        add_option('checkoutkeys_debug_mode', false);
        add_option('checkoutkeys_last_sync', '');
        
        // Flush rewrite rules for API endpoints
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function checkoutkeys() {
    return CheckoutKeys::get_instance();
}

// Start the plugin
checkoutkeys();
