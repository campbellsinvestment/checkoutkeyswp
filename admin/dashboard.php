<?php
/**
 * Admin Dashboard
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get API key for connection status
$api_key = get_option('checkoutkeys_api_key');

// Handle sync action
if (isset($_POST['checkoutkeys_sync']) && check_admin_referer('checkoutkeys_sync')) {
    $api_url = 'https://checkoutkeys.com/api';
    
    if (!empty($api_key)) {
        $response = wp_remote_get($api_url . '/licenses', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 30,
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $status_code = wp_remote_retrieve_response_code($response);
            $data = json_decode($body, true);
            
            // Debug logging
            error_log('CheckoutKeys Sync - Status Code: ' . $status_code);
            error_log('CheckoutKeys Sync - Response Body: ' . $body);
            error_log('CheckoutKeys Sync - Decoded Data: ' . print_r($data, true));
            
            if ($data && isset($data['licenses'])) {
                $db = CheckoutKeys_Database::get_instance();
                $synced = 0;
                
                foreach ($data['licenses'] as $license_data) {
                    // Check if license already exists
                    $existing = $db->get_license_by_key($license_data['key']);
                    
                    // For insert operations
                    $license_array_with_key = array(
                        'license_key' => $license_data['key'],
                        'customer_email' => $license_data['email'],
                        'status' => $license_data['status'],
                        'max_activations' => intval($license_data['max_activations'] ?? 1),
                        'activation_count' => 0,
                        'activated_domains' => '',
                        'created_at' => isset($license_data['created_at']) ? date('Y-m-d H:i:s', strtotime($license_data['created_at'])) : current_time('mysql'),
                        'updated_at' => isset($license_data['updated_at']) ? date('Y-m-d H:i:s', strtotime($license_data['updated_at'])) : current_time('mysql'),
                        'email_sent_at' => isset($license_data['email_sent_at']) && $license_data['email_sent_at'] ? date('Y-m-d H:i:s', strtotime($license_data['email_sent_at'])) : null,
                    );
                    
                    // For update operations (exclude license_key from data array)
                    $license_array_no_key = array(
                        'customer_email' => $license_data['email'],
                        'status' => $license_data['status'],
                        'max_activations' => intval($license_data['max_activations'] ?? 1),
                        'updated_at' => isset($license_data['updated_at']) ? date('Y-m-d H:i:s', strtotime($license_data['updated_at'])) : current_time('mysql'),
                        'email_sent_at' => isset($license_data['email_sent_at']) && $license_data['email_sent_at'] ? date('Y-m-d H:i:s', strtotime($license_data['email_sent_at'])) : null,
                    );
                    
                    if ($existing) {
                        // Update existing license
                        $db->update_license($license_data['key'], $license_array_no_key);
                    } else {
                        // Insert new license
                        $db->insert_license($license_array_with_key);
                    }
                    $synced++;
                }
                
                update_option('checkoutkeys_last_sync', current_time('mysql'));
                echo '<div class="notice notice-success"><p>' . 
                     sprintf(esc_html__('Successfully synced %d licenses from checkoutkeys.com', 'checkoutkeys'), $synced) . 
                     '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . 
                     esc_html__('Invalid response from API. Status: ', 'checkoutkeys') . $status_code . 
                     '. Check debug log for details.' . 
                     '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to connect to checkoutkeys.com: ', 'checkoutkeys') . esc_html($response->get_error_message()) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html__('API key not configured', 'checkoutkeys') . '</p></div>';
    }
}
?>

<div class="wrap">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <img src="<?php echo esc_url(plugins_url('assets/favicon.ico', dirname(__FILE__))); ?>" alt="CheckoutKeys" style="width: 24px; height: 24px;">
            <?php esc_html_e('License Keys', 'checkoutkeys'); ?>
            <?php if (!empty($api_key)) : ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: #46b450; background: #ecf7ed; padding: 4px 10px; border-radius: 12px; font-weight: normal;">
                    <span style="width: 8px; height: 8px; background: #46b450; border-radius: 50%; display: inline-block;"></span>
                    Connected
                </span>
            <?php else : ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: #dc3232; background: #fef0f0; padding: 4px 10px; border-radius: 12px; font-weight: normal;">
                    <span style="width: 8px; height: 8px; background: #dc3232; border-radius: 50%; display: inline-block;"></span>
                    Not Connected
                </span>
            <?php endif; ?>
            <?php if (true) : // Re-enabled - API endpoint now available at /api/licenses ?>
            <form method="post" action="" style="display: inline-block; margin-left: 10px;">
                <?php wp_nonce_field('checkoutkeys_sync'); ?>
                <button type="submit" name="checkoutkeys_sync" class="page-title-action">
                    <?php esc_html_e('Sync from checkoutkeys.com', 'checkoutkeys'); ?>
                </button>
            </form>
            <?php endif; ?>
        </h1>
        <?php 
        $last_sync = get_option('checkoutkeys_last_sync');
        if ($last_sync) :
        ?>
        <span style="font-size: 13px; color: #666;">
            <?php 
            printf(
                esc_html__('Last synced: %s', 'checkoutkeys'),
                esc_html(human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' ago')
            );
            ?>
        </span>
        <?php endif; ?>
    </div>
    
    <?php
    $api_key = get_option('checkoutkeys_api_key');
    if (empty($api_key)) :
    ?>
    <div class="notice notice-warning">
        <p>
            <?php esc_html_e('API key not configured.', 'checkoutkeys'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=checkoutkeys-settings')); ?>">
                <?php esc_html_e('Configure now', 'checkoutkeys'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="checkoutkeys-stats" style="margin: 20px 0;">
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h3><?php esc_html_e('Total Licenses', 'checkoutkeys'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo esc_html($this->db->get_license_count()); ?></p>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h3><?php esc_html_e('Active', 'checkoutkeys'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0; color: #46b450;">
                    <?php 
                    global $wpdb;
                    $active = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}checkoutkeys_licenses WHERE status = %s",
                        'active'
                    ));
                    echo esc_html($active);
                    ?>
                </p>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h3>
                    <?php esc_html_e('Recent Sales', 'checkoutkeys'); ?>
                    <span style="font-size: 11px; color: #666; font-weight: normal;">[Last 30 Days]</span>
                </h3>
                <p style="font-size: 24px; font-weight: bold; margin: 10px 0;">
                    <?php 
                    $recent = 0;
                    $now = current_time('timestamp');
                    foreach ($licenses as $license) {
                        $created = strtotime($license->created_at);
                        $diff_days = floor(($now - $created) / (60 * 60 * 24));
                        if ($diff_days <= 30) {
                            $recent++;
                        }
                    }
                    echo esc_html($recent);
                    ?>
                </p>
            </div>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('License Key', 'checkoutkeys'); ?></th>
                <th><?php esc_html_e('Customer', 'checkoutkeys'); ?></th>
                <th><?php esc_html_e('Status', 'checkoutkeys'); ?></th>
                <th><?php esc_html_e('Created At', 'checkoutkeys'); ?></th>
                <th><?php esc_html_e('Updated At', 'checkoutkeys'); ?></th>
                <th><?php esc_html_e('Email Sent At', 'checkoutkeys'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($licenses)) : ?>
                <?php foreach ($licenses as $license) : ?>
                    <tr>
                        <td>
                            <code style="cursor: pointer; position: relative; padding-right: 25px;" 
                                  onclick="copyToClipboard('<?php echo esc_js($license->license_key); ?>', this)"
                                  title="Click to copy">
                                <?php echo esc_html(substr($license->license_key, 0, 20) . '...'); ?>
                                <span class="dashicons dashicons-clipboard" style="font-size: 14px; position: absolute; right: 5px; top: 50%; transform: translateY(-50%);"></span>
                            </code>
                        </td>
                        <td><?php echo esc_html($license->customer_email); ?></td>
                        <td>
                            <span class="license-status status-<?php echo esc_attr($license->status); ?>">
                                <?php echo esc_html(ucfirst($license->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license->created_at))); ?></td>
                        <td><?php echo (property_exists($license, 'updated_at') && $license->updated_at) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license->updated_at))) : '<span style="color: #999;">—</span>'; ?></td>
                        <td><?php echo (property_exists($license, 'email_sent_at') && $license->email_sent_at) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license->email_sent_at))) : '<span style="color: #999;">—</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No license keys found.', 'checkoutkeys'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function copyToClipboard(text, element) {
    // Create temporary input
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    
    // Visual feedback
    const originalHTML = element.innerHTML;
    const icon = element.querySelector('.dashicons');
    if (icon) {
        icon.classList.remove('dashicons-clipboard');
        icon.classList.add('dashicons-yes');
        icon.style.color = '#46b450';
    }
    
    setTimeout(function() {
        if (icon) {
            icon.classList.remove('dashicons-yes');
            icon.classList.add('dashicons-clipboard');
            icon.style.color = '';
        }
    }, 1500);
}
</script>
