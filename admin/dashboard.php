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

// Handle sync action BEFORE any output
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
            
            if ($data && isset($data['licenses'])) {
                $db = CheckoutKeys_Database::get_instance();
                $synced = 0;
                
                foreach ($data['licenses'] as $license_data) {
                    // Check if license already exists
                    $existing = $db->get_license_by_key($license_data['key']);
                    
                    // Helper function to safely convert timestamps
                    $convert_timestamp = function($timestamp) {
                        if (empty($timestamp)) return null;
                        $time = strtotime($timestamp);
                        if ($time === false || $time < 0) return null;
                        return date('Y-m-d H:i:s', $time);
                    };
                    
                    $created_at = $convert_timestamp($license_data['created_at'] ?? null);
                    $updated_at = $convert_timestamp($license_data['updated_at'] ?? null);
                    $email_sent_at = $convert_timestamp($license_data['email_sent_at'] ?? null);
                    
                    // For insert operations
                    $license_array_with_key = array(
                        'license_key' => $license_data['key'],
                        'customer_email' => !empty($license_data['email']) ? $license_data['email'] : null,
                        'status' => $license_data['status'],
                        'max_activations' => intval($license_data['max_activations'] ?? 1),
                        'activation_count' => 0,
                        'activated_domains' => '',
                        'created_at' => $created_at ?: current_time('mysql'),
                        'updated_at' => $updated_at ?: current_time('mysql'),
                        'email_sent_at' => $email_sent_at,
                    );
                    
                    // For update operations (exclude license_key from data array)
                    $license_array_no_key = array(
                        'customer_email' => !empty($license_data['email']) ? $license_data['email'] : null,
                        'status' => $license_data['status'],
                        'max_activations' => intval($license_data['max_activations'] ?? 1),
                        'created_at' => $created_at ?: current_time('mysql'),
                        'updated_at' => $updated_at ?: current_time('mysql'),
                        'email_sent_at' => $email_sent_at,
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
                
                // Set transient for success message
                set_transient('checkoutkeys_sync_success', $synced, 30);
                
                // JavaScript redirect to avoid output buffer issues
                echo '<script type="text/javascript">window.location.href = "' . 
                     esc_url(admin_url('admin.php?page=checkoutkeys')) . 
                     '";</script>';
                exit;
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

// Display sync success message if redirected with success
$synced_count = get_transient('checkoutkeys_sync_success');
if ($synced_count !== false) {
    delete_transient('checkoutkeys_sync_success');
    $license_word = ($synced_count === 1) ? 'license' : 'licenses';
    echo '<div class="notice notice-success is-dismissible"><p>' . 
         sprintf(esc_html__('Successfully synced %d ' . $license_word . ' from checkoutkeys.com', 'checkoutkeys'), $synced_count) . 
         '</p></div>';
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
    
    // Fetch subscription status from CheckoutKeys API
    $subscription_data = null;
    if (!empty($api_key)) {
        $api_url = 'https://checkoutkeys.com/api';
        $response = wp_remote_get($api_url . '/subscription/status', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 15,
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $subscription_data = json_decode($body, true);
        }
    }
    
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
    
    <?php
    // Show upgrade notice if required
    if ($subscription_data && isset($subscription_data['upgradeRequired']) && $subscription_data['upgradeRequired']) :
    ?>
    <div class="notice notice-error" style="border-left-color: #f0ad4e;">
        <p>
            <strong><?php esc_html_e('⚠️ Upgrade Required', 'checkoutkeys'); ?></strong><br>
            <?php 
            printf(
                esc_html__('You\'ve reached the limit of your current %s plan (%d license keys). ', 'checkoutkeys'),
                esc_html($subscription_data['plan']['name']),
                intval($subscription_data['plan']['limit'])
            );
            ?>
            <a href="https://checkoutkeys.com/pricing" target="_blank" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e('Upgrade Now', 'checkoutkeys'); ?>
            </a>
        </p>
    </div>
    <?php 
    // Show approaching limit warning
    elseif ($subscription_data && isset($subscription_data['usagePercentage']) && $subscription_data['usagePercentage'] >= 80 && $subscription_data['usagePercentage'] < 100) :
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('⚠️ Approaching Plan Limit', 'checkoutkeys'); ?></strong><br>
            <?php 
            printf(
                esc_html__('You\'ve used %d%% of your %s plan limit. Consider upgrading to avoid service disruption.', 'checkoutkeys'),
                intval($subscription_data['usagePercentage']),
                esc_html($subscription_data['plan']['name'])
            );
            ?>
            <a href="https://checkoutkeys.com/pricing" target="_blank" style="margin-left: 10px;">
                <?php esc_html_e('View Plans', 'checkoutkeys'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if ($subscription_data && isset($subscription_data['plan'])) : ?>
    <!-- Plan Card - Full Width -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin: 20px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px;">
                <h3 style="color: #23282d; font-size: 14px; margin: 0 0 10px 0; opacity: 0.7;"><?php esc_html_e('Current Plan', 'checkoutkeys'); ?></h3>
                <p style="font-size: 28px; font-weight: bold; margin: 0; color: #0073aa;"><?php echo esc_html($subscription_data['plan']['name']); ?></p>
                <p style="font-size: 14px; margin: 10px 0 0 0; color: #666;">
                    <?php 
                    if ($subscription_data['plan']['price'] > 0) {
                        printf(esc_html__('$%d/month', 'checkoutkeys'), intval($subscription_data['plan']['price']));
                    } else {
                        esc_html_e('Free Forever', 'checkoutkeys');
                    }
                    ?>
                </p>
            </div>
            <div style="flex: 2; min-width: 300px;">
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;"><?php esc_html_e('Usage', 'checkoutkeys'); ?></div>
                <div style="font-size: 16px; font-weight: bold; color: #23282d; margin-bottom: 8px;">
                    <?php echo esc_html($subscription_data['licenseKeysCount']); ?> / <?php echo esc_html($subscription_data['plan']['limit']); ?>
                    <span style="font-size: 12px; font-weight: normal; color: #666;"><?php esc_html_e('keys', 'checkoutkeys'); ?></span>
                </div>
                <div style="width: 100%; height: 8px; background: #f0f0f1; border-radius: 4px; overflow: hidden;">
                    <?php 
                    $usage_pct = intval($subscription_data['usagePercentage']);
                    $bar_color = '#0073aa'; // WordPress blue
                    if ($usage_pct >= 100) {
                        $bar_color = '#dc3232'; // WordPress red
                    } elseif ($usage_pct >= 80) {
                        $bar_color = '#f56e28'; // WordPress orange
                    }
                    $bar_width = min(100, $usage_pct);
                    ?>
                    <div style="height: 100%; background: <?php echo esc_attr($bar_color); ?>; border-radius: 4px; width: <?php echo esc_attr($bar_width); ?>%;"></div>
                </div>
            </div>
            <?php if ($subscription_data['plan']['name'] === 'Free' || $subscription_data['usagePercentage'] >= 80) : ?>
            <div>
                <a href="https://checkoutkeys.com/pricing" target="_blank" class="button button-primary">
                    <?php esc_html_e('Upgrade Plan', 'checkoutkeys'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="checkoutkeys-stats" style="margin: 20px 0;">
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3><?php esc_html_e('Total Licenses', 'checkoutkeys'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo esc_html($this->db->get_license_count()); ?></p>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
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
                <th style="width: 20%;"><?php esc_html_e('License Key', 'checkoutkeys'); ?></th>
                <th style="width: 18%;"><?php esc_html_e('Customer', 'checkoutkeys'); ?></th>
                <th style="width: 10%;"><?php esc_html_e('Status', 'checkoutkeys'); ?></th>
                <th style="width: 14%;"><?php esc_html_e('Created At', 'checkoutkeys'); ?></th>
                <th style="width: 14%;"><?php esc_html_e('Updated At', 'checkoutkeys'); ?></th>
                <th style="width: 14%;"><?php esc_html_e('Email Sent At', 'checkoutkeys'); ?></th>
                <th style="width: 10%; text-align: right;"><?php esc_html_e('Actions', 'checkoutkeys'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($licenses)) : ?>
                <?php foreach ($licenses as $license) : ?>
                    <tr>
                        <td>
                            <code style="cursor: pointer; position: relative; padding-right: 25px; display: block; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                  onclick="copyToClipboard('<?php echo esc_js($license->license_key); ?>', this)"
                                  title="<?php echo esc_attr($license->license_key); ?> - Click to copy">
                                <?php echo esc_html(substr($license->license_key, 0, 30) . '...'); ?>
                                <span class="dashicons dashicons-clipboard" style="font-size: 14px; position: absolute; right: 5px; top: 50%; transform: translateY(-50%);"></span>
                            </code>
                        </td>
                        <td style="word-break: break-word;"><?php echo esc_html($license->customer_email); ?></td>
                        <td>
                            <span class="license-status status-<?php echo esc_attr($license->status); ?>">
                                <?php echo esc_html(ucfirst($license->status)); ?>
                            </span>
                        </td>
                        <td><?php echo ($license->created_at && strtotime($license->created_at) !== false) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license->created_at))) : '<span style="color: #999;">—</span>'; ?></td>
                        <td><?php echo (property_exists($license, 'updated_at') && $license->updated_at && strtotime($license->updated_at) !== false) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license->updated_at))) : '<span style="color: #999;">—</span>'; ?></td>
                        <td><?php echo (property_exists($license, 'email_sent_at') && $license->email_sent_at && strtotime($license->email_sent_at) !== false) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license->email_sent_at))) : '<span style="color: #999;">—</span>'; ?></td>
                        <td style="text-align: right;">
                            <button type="button" 
                                    class="button button-small toggle-license-btn <?php echo ($license->status === 'active') ? 'deactivate-btn' : 'activate-btn'; ?>"
                                    data-license-key="<?php echo esc_attr($license->license_key); ?>"
                                    data-action="<?php echo ($license->status === 'active') ? 'deactivate' : 'activate'; ?>"
                                    style="background: <?php echo ($license->status === 'active') ? '#dc3232' : '#46b450'; ?>; color: white; border-color: <?php echo ($license->status === 'active') ? '#dc3232' : '#46b450'; ?>;">
                                <?php echo ($license->status === 'active') ? esc_html__('Deactivate', 'checkoutkeys') : esc_html__('Activate', 'checkoutkeys'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No license keys found.', 'checkoutkeys'); ?></td>
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

// Handle toggle license status
jQuery(document).ready(function($) {
    $('.toggle-license-btn').on('click', function() {
        var button = $(this);
        var licenseKey = button.data('license-key');
        var action = button.data('action');
        var row = button.closest('tr');
        var statusCell = row.find('.license-status');
        
        // Disable button and show loading
        button.prop('disabled', true);
        var originalText = button.text();
        button.text('...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'checkoutkeys_toggle_license',
                license_key: licenseKey,
                toggle_action: action,
                nonce: '<?php echo wp_create_nonce('checkoutkeys_ajax'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update status badge
                    statusCell.removeClass('status-active status-inactive');
                    statusCell.addClass('status-' + response.data.new_status);
                    statusCell.text(response.data.new_status.charAt(0).toUpperCase() + response.data.new_status.slice(1));
                    
                    // Update button
                    button.data('action', response.data.new_action);
                    button.text(response.data.new_button_text);
                    button.removeClass('activate-btn deactivate-btn');
                    button.addClass(response.data.new_button_class);
                    
                    // Update button colors
                    var newColor = (response.data.new_action === 'deactivate') ? '#dc3232' : '#46b450';
                    button.css({
                        'background': newColor,
                        'border-color': newColor
                    });
                    
                    // Show success message
                    var notice = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + response.data.message + '</p></div>');
                    $('.wrap > h1').after(notice);
                    setTimeout(function() { notice.fadeOut(); }, 3000);
                } else {
                    alert('Error: ' + response.data.message);
                    button.text(originalText);
                }
                button.prop('disabled', false);
            },
            error: function() {
                alert('Failed to update license status. Please try again.');
                button.text(originalText);
                button.prop('disabled', false);
            }
        });
    });
});
</script>
