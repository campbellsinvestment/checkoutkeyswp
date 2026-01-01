<?php
/**
 * Database Handler
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CheckoutKeys_Database {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Table name for license keys
     */
    private $license_table;
    
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
        global $wpdb;
        $this->license_table = $wpdb->prefix . 'checkoutkeys_licenses';
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'checkoutkeys_licenses';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            activation_count int(11) DEFAULT 0 NOT NULL,
            max_activations int(11) DEFAULT 1 NOT NULL,
            activated_domains text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
            email_sent_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY license_key (license_key),
            KEY customer_email (customer_email),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Run migration to add new columns if they don't exist
        self::migrate_add_timestamp_columns();
    }
    
    /**
     * Migration: Add updated_at and email_sent_at columns
     */
    public static function migrate_add_timestamp_columns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkoutkeys_licenses';
        
        // Check if columns exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        $column_names = array_column($columns, 'Field');
        
        // Add updated_at column if it doesn't exist
        if (!in_array('updated_at', $column_names)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
        
        // Add email_sent_at column if it doesn't exist
        if (!in_array('email_sent_at', $column_names)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN email_sent_at datetime DEFAULT NULL AFTER updated_at");
        }
    }
    
    /**
     * Insert license key
     */
    public function insert_license($data) {
        global $wpdb;
        
        // Dynamic format array based on the data being inserted
        $formats = array();
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        $result = $wpdb->insert(
            $this->license_table,
            $data,
            $formats
        );
        
        if ($result === false) {
            error_log('CheckoutKeys Insert Error: ' . $wpdb->last_error);
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get license by key
     */
    public function get_license_by_key($license_key) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->license_table} WHERE license_key = %s",
                $license_key
            )
        );
    }
    
    /**
     * Get licenses by customer email
     */
    public function get_licenses_by_email($email) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->license_table} WHERE customer_email = %s ORDER BY created_at DESC",
                $email
            )
        );
    }
    
    /**
     * Update license
     */
    public function update_license($license_key, $data) {
        global $wpdb;
        
        // Dynamic format array based on data
        $formats = array();
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $wpdb->update(
            $this->license_table,
            $data,
            array('license_key' => $license_key),
            $formats,
            array('%s')
        );
    }
    
    /**
     * Get all licenses with pagination
     */
    public function get_all_licenses($limit = 20, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->license_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    /**
     * Get license count
     */
    public function get_license_count() {
        global $wpdb;
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->license_table}");
    }
}
