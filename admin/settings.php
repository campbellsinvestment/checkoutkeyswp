<?php
/**
 * Settings Page
 *
 * @package CheckoutKeys
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['checkoutkeys_save_settings']) && check_admin_referer('checkoutkeys_settings')) {
    $api_key = sanitize_text_field($_POST['checkoutkeys_api_key']);
    $api_url = esc_url_raw($_POST['checkoutkeys_api_url']);
    
    // Validate API key before saving
    if (!empty($api_key)) {
        $response = wp_remote_get($api_url . '/licenses', array(
            'headers' => array(
                'x-api-key' => $api_key,
            ),
            'timeout' => 15,
        ));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                update_option('checkoutkeys_api_key', $api_key);
                update_option('checkoutkeys_api_url', $api_url);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully. API key is valid.', 'checkoutkeys') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid API key. Settings not saved.', 'checkoutkeys') . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not verify API key. Please check your connection.', 'checkoutkeys') . '</p></div>';
        }
    } else {
        // Allow clearing the API key
        update_option('checkoutkeys_api_key', '');
        update_option('checkoutkeys_api_url', $api_url);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'checkoutkeys') . '</p></div>';
    }
}

$api_key = get_option('checkoutkeys_api_key', '');
$api_url = get_option('checkoutkeys_api_url', 'https://checkoutkeys.com/api');
?>

<div class="wrap">
    <h1 style="display: flex; align-items: center; gap: 10px;">
        <img src="<?php echo esc_url(plugins_url('assets/favicon.ico', dirname(__FILE__))); ?>" alt="CheckoutKeys" style="width: 24px; height: 24px;">
        <?php esc_html_e('CheckoutKeys Settings', 'checkoutkeys'); ?>
    </h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('checkoutkeys_settings'); ?>
        
        <p class="description">
            <?php esc_html_e('Get your API credentials from', 'checkoutkeys'); ?> 
            <a href="https://checkoutkeys.com/dashboard" target="_blank">https://checkoutkeys.com/dashboard</a>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="checkoutkeys_api_key"><?php esc_html_e('API Key', 'checkoutkeys'); ?></label>
                </th>
                <td>
                    <div style="position: relative; display: inline-block;">
                        <input type="text" 
                               name="checkoutkeys_api_key" 
                               id="checkoutkeys_api_key" 
                               value="<?php echo esc_attr($api_key); ?>"
                               class="regular-text code"
                               autocomplete="off"
                               placeholder="Paste your API key here"
                               style="padding-right: 35px; transition: border-color 0.3s;" />
                        <span id="api_key_indicator" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: none;">
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 18px;"></span>
                        </span>
                    </div>
                    <p class="description" id="api_key_message"><?php esc_html_e('Your CheckoutKeys API key from the dashboard', 'checkoutkeys'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Settings', 'checkoutkeys'), 'primary', 'checkoutkeys_save_settings', true, array('id' => 'save_settings_btn')); ?>
    </form>
</div>

<style>
@keyframes rotation {
    from { transform: translateY(-50%) rotate(0deg); }
    to { transform: translateY(-50%) rotate(359deg); }
}
.api-key-valid {
    border-color: #46b450 !important;
    box-shadow: 0 0 2px rgba(70, 180, 80, 0.8) !important;
}
.api-key-invalid {
    border-color: #dc3232 !important;
    box-shadow: 0 0 2px rgba(220, 50, 50, 0.8) !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    var validationTimeout;
    var isValidKey = <?php echo !empty($api_key) ? 'true' : 'false'; ?>;
    
    // Validate on page load if there's an existing key
    <?php if (!empty($api_key)) : ?>
    validateApiKey('<?php echo esc_js($api_key); ?>');
    <?php endif; ?>
    
    $('#checkoutkeys_api_key').on('input', function() {
        var apiKey = $(this).val().trim();
        
        // Clear previous timeout
        clearTimeout(validationTimeout);
        
        // Reset UI
        $('#api_key_indicator').hide();
        $(this).removeClass('api-key-valid api-key-invalid');
        $('#api_key_message').html('<?php esc_html_e('Your CheckoutKeys API key from the dashboard', 'checkoutkeys'); ?>').css('color', '');
        isValidKey = false;
        
        if (apiKey.length > 10) {
            // Debounce validation
            validationTimeout = setTimeout(function() {
                validateApiKey(apiKey);
            }, 800);
        }
    });
    
    function validateApiKey(apiKey) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'checkoutkeys_validate_api_key',
                api_key: apiKey,
                nonce: '<?php echo wp_create_nonce('checkoutkeys_validate_key'); ?>'
            },
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    $('#checkoutkeys_api_key').addClass('api-key-valid');
                    $('#api_key_indicator').show();
                    $('#api_key_message').html('<?php esc_html_e('✓ Valid API key', 'checkoutkeys'); ?>').css('color', '#46b450');
                    isValidKey = true;
                } else {
                    showInvalid();
                }
            },
            error: function() {
                showInvalid();
            }
        });
    }
    
    function showInvalid() {
        $('#checkoutkeys_api_key').addClass('api-key-invalid');
        $('#api_key_message').html('<?php esc_html_e('✗ Invalid API key', 'checkoutkeys'); ?>').css('color', '#dc3232');
        isValidKey = false;
    }
    
    // Prevent form submission if API key is invalid
    $('form').on('submit', function(e) {
        var apiKey = $('#checkoutkeys_api_key').val().trim();
        
        if (apiKey.length > 0 && !isValidKey) {
            e.preventDefault();
            $('#api_key_message').html('<?php esc_html_e('✗ Please enter a valid API key before saving', 'checkoutkeys'); ?>').css('color', '#dc3232');
            $('#checkoutkeys_api_key').addClass('api-key-invalid').focus();
            return false;
        }
    });
});
</script>
