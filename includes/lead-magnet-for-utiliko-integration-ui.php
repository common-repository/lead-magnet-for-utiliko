<?php
if (!defined('ABSPATH')) {
    exit;
}

function lead_magnet_for_utiliko_render_integration_ui() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'lead-magnet-for-utiliko'));
    }

    $crm_url = get_option('lead-magnet-for-utiliko_crm_url', '');

    ?>
    <div class="wrap">
        <div class="logo_header">
            <img src="<?php echo esc_url(LEAD_MAGNET_FOR_UTILIKO_URL . 'assets/img/utiliko_logo.webp'); ?>" class="logo-icon" alt="Utiliko Logo">
        </div>

        <div class="gs-parts-wpform crmInstructions">
            <div class="card-wp">
                <input type="hidden" name="redirect_auth_wpforms" id="redirect_auth_wpforms"
                       value="<?php echo (isset($header)) ? esc_attr($header) : ''; ?>">
                <span class="wpforms-setting-field log-setting">
                    <?php if (empty(get_option('wpform_gs_token'))) { ?>
                        <div class="wpform-gs-alert-kk" id="google-drive-msg">
                            <p class="wpform-gs-alert-heading">
                                <?php echo esc_html__('Authenticate with CRM URL and follow these steps:', 'lead-magnet-for-utiliko'); ?>
                            </p>
                            <ol class="wpform-gs-alert-steps">
                                <li><?php echo esc_html__('Enter the data in the input box.', 'lead-magnet-for-utiliko'); ?></li>
                                <li><?php echo esc_html__('Click on the "Connect" button.', 'lead-magnet-for-utiliko'); ?></li>
                                <li><?php echo esc_html__('You will be redirected to the CRM.', 'lead-magnet-for-utiliko'); ?></li>
                            </ol>
                        </div>
                    <?php } ?>
                </span>
            </div>
        </div>

        <form id="crmForm" class="frm_ip_URL">
            <div class="form-field-mapping crmUI">
                <label for="input_data" class="crmURL"><?php echo esc_html__('CRM URL:', 'lead-magnet-for-utiliko'); ?></label>
                <input type="text" name="input_data" id="input_data" required>
            </div>
            <div class="form-field-mapping crmUIBtn">
                <input type="submit" value="<?php echo esc_attr__('Connect', 'lead-magnet-for-utiliko'); ?>" class="button-primary-submit">
            </div>
        </form>

    </div>
    <?php
}

function lead_magnet_update_crm_entry($table_name, $crm_url, $crm_token) {
    global $wpdb;
    $table_name = sanitize_key($table_name);

    $cache_key = 'crm_entry_' . md5($crm_url);
    $existing_token = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');

    if ($existing_token === false) {
        $existing_token = $wpdb->get_var($wpdb->prepare("SELECT crm_token FROM $table_name WHERE crm_url = %s", $crm_url));
        wp_cache_set($cache_key, $existing_token, 'lead_magnet_for_utiliko', 3600);
    }

    $result = $wpdb->update(
        $table_name,
        array('crm_token' => $crm_token),
        array('crm_url' => $crm_url),
        array('%s'),
        array('%s')
    );
    if ($result !== false) {
        wp_cache_set($cache_key, $crm_token, 'lead_magnet_for_utiliko', 3600);
    } else {
        return false;
    }

    return $result;
}

function lead_magnet_insert_crm_entry($table_name, $crm_url, $crm_token) {
    global $wpdb;
    $table_name = sanitize_key($table_name);

    $data = array(
        'crm_url'   => sanitize_text_field($crm_url),
        'crm_token' => sanitize_text_field($crm_token)
    );

    $cache_key = 'crm_entry_' . md5($crm_url);
    $cached_entry = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');

    if ($cached_entry === false) {
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%s', '%s')
        );
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return array('success' => false, 'message' => esc_html__('Database insert failed. Please try again.', 'lead-magnet-for-utiliko'));
            }
            return false;
        }
        wp_cache_set($cache_key, $data, 'lead_magnet_for_utiliko', 3600);
    }

    return true;
}

function lead_magnet_save_crm_url() {
    check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => esc_html__('Permission denied', 'lead-magnet-for-utiliko')));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cncrm_entries';

    $crm_url = isset($_POST['crm_url']) ? sanitize_text_field(wp_unslash($_POST['crm_url'])) : '';
    $crm_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';

    if (!$crm_url || !$crm_token) {
        wp_send_json_error(array('message' => esc_html__('Invalid CRM URL or Access Token', 'lead-magnet-for-utiliko')));
    }
        $cache_key = 'crm_url_' . md5($crm_url);
        $cache_group = 'lead_magnet_for_utiliko';
        $existing_entry = wp_cache_get($cache_key, $cache_group);

    if (false === $existing_entry) {
            $existing_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name cncrm_entries WHERE crm_url = %s", $crm_url));
            wp_cache_set($cache_key, $existing_entry, $cache_group, 3600);
        }

    if ($existing_entry) {
        $result = lead_magnet_update_crm_entry($table_name, $crm_url, $crm_token);
    } else {
        $result = lead_magnet_insert_crm_entry($table_name, $crm_url, $crm_token);
    }

    if ($result === false) {
        wp_send_json_error(array('message' => esc_html__('Database error:', 'lead-magnet-for-utiliko') . ' ' . esc_html($wpdb->last_error)));
    } else {
        wp_cache_delete($cache_key, $cache_group);
        wp_send_json_success();
    }
}

add_action('wp_ajax_lead_magnet_save_crm_url', 'lead_magnet_save_crm_url');