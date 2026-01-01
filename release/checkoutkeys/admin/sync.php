<?php
/**
 * Sync Page
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle sync action
if (isset($_POST['checkoutkeys_sync']) && check_admin_referer('checkoutkeys_sync')) {
    $api_key = get_option('checkoutkeys_api_key');
    $api_url = get_option('checkoutkeys_api_url', 'https://checkoutkeys.com/api');
    
    if (empty($api_key)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('API key not configured. Please configure in settings.', 'checkoutkeys') . '</p></div>';
    } else {
        // Perform sync with CheckoutKeys.com API
        $response = wp_remote_get($api_url . '/licenses', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Sync failed: ', 'checkoutkeys') . esc_html($response->get_error_message()) . '</p></div>';
            
            // Log error if debug mode enabled
            if (get_option('checkoutkeys_debug_mode')) {
                error_log('CheckoutKeys Sync Error: ' . $response->get_error_message());
            }
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $licenses_data = json_decode($body, true);
                
                if (is_array($licenses_data) && isset($licenses_data['licenses'])) {
                    $db = CheckoutKeys_Database::get_instance();
                    $synced = 0;
                    
                    foreach ($licenses_data['licenses'] as $license) {
                        // Insert or update license
                        $existing = $db->get_license_by_key($license['license_key']);
                        
                        if ($existing) {
                            // Update existing
                            $db->update_license($license['license_key'], array(
                                'status' => $license['status'],
                                'activation_count' => $license['activation_count']
                            ));
                        } else {
                            // Insert new
                            $db->insert_license(array(
                                'license_key' => $license['license_key'],
                                'stripe_customer_id' => $license['stripe_customer_id'],
                                'customer_email' => $license['customer_email'],
                                'status' => $license['status'],
                                'activation_count' => $license['activation_count'],
                                'max_activations' => $license['max_activations'],
                                'activated_domains' => json_encode($license['activated_domains'] ?? []),
                                'created_at' => $license['created_at'],
                                'expires_at' => $license['expires_at'] ?? null
                            ));
                        }
                        $synced++;
                    }
                    
                    update_option('checkoutkeys_last_sync', current_time('mysql'));
                    echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Successfully synced %d licenses from CheckoutKeys.com', 'checkoutkeys'), $synced) . '</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>' . esc_html__('No licenses found to sync.', 'checkoutkeys') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Sync failed with status code: ', 'checkoutkeys') . esc_html($status_code) . '</p></div>';
                
                // Log error if debug mode enabled
                if (get_option('checkoutkeys_debug_mode')) {
                    error_log('CheckoutKeys Sync Error: Status ' . $status_code . ' - ' . $body);
                }
            }
        }
    }
}

$last_sync = get_option('checkoutkeys_last_sync');
$api_key = get_option('checkoutkeys_api_key');
?>

<div class="wrap">
    <h1><?php esc_html_e('Sync Licenses', 'checkoutkeys'); ?></h1>
    
    <div class="card" style="max-width: 600px;">
        <h2><?php esc_html_e('Sync from checkoutkeys.com', 'checkoutkeys'); ?></h2>
        <p><?php esc_html_e('This will fetch all your licenses from checkoutkeys.com and update your local WordPress database.', 'checkoutkeys'); ?></p>
        
        <?php if (empty($api_key)) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e('API key not configured.', 'checkoutkeys'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=checkoutkeys-settings')); ?>">
                        <?php esc_html_e('Configure now', 'checkoutkeys'); ?>
                    </a>
                </p>
            </div>
        <?php else : ?>
            <?php if ($last_sync && strtotime($last_sync) !== false) : ?>
                <p>
                    <strong><?php esc_html_e('Last sync:', 'checkoutkeys'); ?></strong>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))); ?>
                    (<?php echo esc_html(human_time_diff(strtotime($last_sync), current_time('timestamp'))); ?> ago)
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e('No sync has been performed yet.', 'checkoutkeys'); ?></p>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('checkoutkeys_sync'); ?>
                <p>
                    <button type="submit" name="checkoutkeys_sync" class="button button-primary button-hero">
                        <?php esc_html_e('Sync Now', 'checkoutkeys'); ?>
                    </button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="card" style="max-width: 600px; margin-top: 20px;">
        <h2><?php esc_html_e('Automatic Sync', 'checkoutkeys'); ?></h2>
        <p><?php esc_html_e('You can set up a WordPress cron job to automatically sync licenses daily. Add this to your theme\'s functions.php or a custom plugin:', 'checkoutkeys'); ?></p>
        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><code>add_action('checkoutkeys_daily_sync', function() {
    // Trigger sync programmatically
    do_action('checkoutkeys_sync_licenses');
});

if (!wp_next_scheduled('checkoutkeys_daily_sync')) {
    wp_schedule_event(time(), 'daily', 'checkoutkeys_daily_sync');
}</code></pre>
    </div>
</div>
