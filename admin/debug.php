<?php
/**
 * Logs Page
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get WordPress debug log file path
$log_file = WP_CONTENT_DIR . '/debug.log';
$log_entries = array();

if (file_exists($log_file)) {
    // Read last 500 lines of the log file
    $file = new SplFileObject($log_file, 'r');
    $file->seek(PHP_INT_MAX);
    $total_lines = $file->key();
    $start_line = max(0, $total_lines - 500);
    
    $file->seek($start_line);
    $raw_lines = array();
    while (!$file->eof()) {
        $line = $file->current();
        if (!empty(trim($line))) {
            $raw_lines[] = $line;
        }
        $file->next();
    }
    
    // Parse CheckoutKeys-related logs into structured entries
    foreach ($raw_lines as $line) {
        if (stripos($line, 'checkoutkeys') === false) {
            continue;
        }
        
        // Parse timestamp and message
        if (preg_match('/\[(.*?)\]\s+(.*)/', $line, $matches)) {
            $timestamp = $matches[1];
            $message = $matches[2];
            
            // Skip verbose HTML responses
            if (stripos($message, '<!DOCTYPE') !== false || 
                stripos($message, '<html') !== false ||
                strlen($message) > 500) {
                continue;
            }
            
            // Extract structured data
            $entry = array(
                'timestamp' => $timestamp,
                'message' => $message,
                'type' => 'info'
            );
            
            // Determine type and extract key info
            if (stripos($message, 'Status Code:') !== false) {
                preg_match('/Status Code:\s*(\d+)/', $message, $code_match);
                $entry['status_code'] = $code_match[1] ?? 'N/A';
                $entry['type'] = ($entry['status_code'] >= 200 && $entry['status_code'] < 300) ? 'success' : 'error';
                $entry['message'] = 'API Request - Status: ' . $entry['status_code'];
            } elseif (stripos($message, 'Sync') !== false) {
                $entry['type'] = 'sync';
            } elseif (stripos($message, 'error') !== false || stripos($message, 'failed') !== false) {
                $entry['type'] = 'error';
            } elseif (stripos($message, 'success') !== false) {
                $entry['type'] = 'success';
            }
            
            $log_entries[] = $entry;
        }
    }
    
    // Keep only last 25 parsed entries
    $log_entries = array_slice($log_entries, -25);
}

// Handle clear logs action
if (isset($_POST['checkoutkeys_clear_logs']) && check_admin_referer('checkoutkeys_clear_logs')) {
    if (file_exists($log_file) && is_writable($log_file)) {
        file_put_contents($log_file, '');
        echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared.', 'checkoutkeys') . '</p></div>';
        $log_entries = array();
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Logs', 'checkoutkeys'); ?></h1>
    
    <div class="card" style="max-width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div>
                <h2 style="margin: 0;"><?php esc_html_e('CheckoutKeys Activity', 'checkoutkeys'); ?></h2>
                <p class="description" style="margin: 5px 0 0 0;">
                    <?php esc_html_e('Showing CheckoutKeys API activity (last 25 entries)', 'checkoutkeys'); ?>
                </p>
            </div>
            <?php if (file_exists($log_file)) : ?>
                <form method="post" action="" style="margin: 0;">
                    <?php wp_nonce_field('checkoutkeys_clear_logs'); ?>
                    <button type="submit" name="checkoutkeys_clear_logs" class="button" onclick="return confirm('This will clear the entire debug.log file. Continue?');">
                        <?php esc_html_e('Clear All Logs', 'checkoutkeys'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
            
            <?php if (!empty($log_entries)) : ?>
                <div style="max-height: 600px; overflow-y: auto; overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="table-layout: auto;">
                        <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                            <tr>
                                <th style="width: 200px; white-space: nowrap;"><?php esc_html_e('Time', 'checkoutkeys'); ?></th>
                                <th style="width: 100px; white-space: nowrap;"><?php esc_html_e('Type', 'checkoutkeys'); ?></th>
                                <th style="min-width: 600px;"><?php esc_html_e('Message', 'checkoutkeys'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($log_entries) as $entry) : 
                                $badge_colors = array(
                                    'success' => 'background: #46b450; color: white;',
                                    'error' => 'background: #dc3232; color: white;',
                                    'sync' => 'background: #00a0d2; color: white;',
                                    'info' => 'background: #72aee6; color: white;'
                                );
                                $badge_style = $badge_colors[$entry['type']] ?? $badge_colors['info'];
                            ?>
                                <tr>
                                    <td>
                                        <span style="font-family: monospace; font-size: 11px;">
                                            <?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($entry['timestamp']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="<?php echo esc_attr($badge_style); ?> padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                            <?php echo esc_html($entry['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-family: monospace; font-size: 12px;">
                                            <?php echo esc_html($entry['message']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (file_exists($log_file)) : ?>
                <p style="padding: 20px; text-align: center; color: #666; background: #f5f5f5; border-radius: 4px;">
                    <?php esc_html_e('No CheckoutKeys logs found. Perform actions like syncing licenses to generate logs.', 'checkoutkeys'); ?>
                </p>
            <?php else : ?>
                <p style="padding: 20px; text-align: center; color: #666; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                    <?php esc_html_e('Debug log file not found. Make sure WP_DEBUG and WP_DEBUG_LOG are enabled in wp-config.php', 'checkoutkeys'); ?>
                </p>
            <?php endif; ?>
    </div>
</div>
