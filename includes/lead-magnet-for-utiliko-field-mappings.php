<?php

if (!defined('ABSPATH')) {
    exit;
}

class lead_magnet_for_utiliko_Field_Mappings {
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wpforms_process_complete', array($this, 'process_form_submission'), 10, 4);
        add_action('wp_ajax_saveField_mappings', array($this, 'saveField_mappings'));
        add_action('wpforms_process_complete', array($this, 'send_to_custom_crm'), 10, 4);
        add_action('wp_ajax_send_data_to_crm', array($this, 'send_data_to_crm'));
        add_action('wp_ajax_nopriv_send_data_to_crm', array($this, 'send_data_to_crm'));
        add_action('wp_ajax_handle_wpforms_ajax_submission', array($this, 'handle_wpforms_ajax_submission'));
        add_action('wp_ajax_nopriv_handle_wpforms_ajax_submission', array($this, 'handle_wpforms_ajax_submission'));
        add_action('wp_ajax_get_field_mappings', array($this,'get_field_mappings'));
        add_action('wp_ajax_nopriv_get_field_mappings', array($this,'get_field_mappings'));

    }

    public function register_settings() {
        register_setting('lead_magnet_for_utiliko_options_group', 'lead_magnet_for_utiliko_field_mappings', array($this, 'sanitize'));
        add_settings_section(
            'lead-magnet-for-utiliko_section',
            __('CRM Field Mappings', 'lead-magnet-for-utiliko'),
            null,
            'lead-magnet-for-utiliko-config'
        );

        $fields = $this->get_wpform_fields();
        if (!empty($fields)) {
            foreach ($fields as $field) {
                add_settings_field(
                    $field['id'],
                    $field['label'],
                    array($this, 'field_mapping_dropdown'),
                    'lead-magnet-for-utiliko-config',
                    'lead-magnet-for-utiliko_section',
                    array('field' => $field)
                );
            }
        }
    }

    public function field_mapping_dropdown($args) {
        $field = $args['field'];
        $mappings = get_option('lead_magnet_for_utiliko_field_mappings', array());
        $value = isset($mappings[$field['id']]) ? $mappings[$field['id']] : '';
        echo '<select name="lead_magnet_for_utiliko_field_mappings[' . esc_attr($field['id']) . ']">
            <option value="">' . esc_html__('Select Mapping', 'lead-magnet-for-utiliko') . '</option>
            <option value="leadName"' . selected($value, 'leadName', false) . '>Lead Name</option>
            <option value="firstName"' . selected($value, 'firstName', false) . '>First Name</option>
            <option value="lastName"' . selected($value, 'lastName', false) . '>Last Name</option>
            <option value="businessEmail"' . selected($value, 'businessEmail', false) . '>Business Email</option>
            <option value="CompanyName"' . selected($value, 'companyName', false) . '>Company Name</option>
            <option value="companyPhone"' . selected($value, 'companyPhone', false) . '>Company Phone</option>
            <option value="phone"' . selected($value, 'phone', false) . '>Phone</option>
            <option value="email"' . selected($value, 'email', false) . '>Email</option>
            <option value="location"' . selected($value, 'location', false) . '>Location</option>
            <option value="address"' . selected($value, 'address', false) . '>Address</option>
            <option value="message"' . selected($value, 'message', false) . '>Message</option>
        </select>';
    }

    public function sanitize($input) {
        $sanitized_input = array();
        foreach ($input as $key => $value) {
            $sanitized_input[$key] = sanitize_text_field($value);
        }
        return $sanitized_input;
    }

    function saveField_mappings() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Permission denied', 'lead-magnet-for-utiliko')));
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'cncrm_form_mappings';
        if (!isset($_POST['form_id'], $_POST['form_name'], $_POST['mappings'])) {
            wp_send_json_error(array('message' => esc_html__('Required fields are missing.', 'lead-magnet-for-utiliko')));
            return;
        }
    
        $form_id = intval($_POST['form_id']);
        $form_name = sanitize_text_field(wp_unslash($_POST['form_name']));
        $mappings = isset($_POST['mappings']) && is_array($_POST['mappings']) ? array_map('sanitize_text_field', wp_unslash($_POST['mappings'])) : array();
        $mappings_serialized = maybe_serialize($mappings);
        $cache_key = 'cncrm_form_mapping_' . $form_id;
        $existing_mapping = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');
    
        if ($existing_mapping === false) {
            $existing_mapping = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE form_id = %d", $form_id));
            wp_cache_set($cache_key, $existing_mapping, 'lead_magnet_for_utiliko', 3600);
        }
    
        if ($existing_mapping) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'form_name' => $form_name,
                    'mappings' => $mappings_serialized,
                ),
                array('form_id' => $form_id),
                array('%s', '%s'),
                array('%d')
            );

            if ($result !== false) {
                wp_cache_delete($cache_key, 'lead_magnet_for_utiliko');
                wp_send_json_success(array('message' => esc_html__('Form mappings updated successfully.', 'lead-magnet-for-utiliko')));
            } else {
                wp_send_json_error(array('message' => esc_html__('Failed to update form mappings.', 'lead-magnet-for-utiliko')));
            }
            wp_cache_delete($cache_key, 'lead_magnet_for_utiliko');
        } else {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'form_id' => $form_id,
                    'form_name' => $form_name,
                    'mappings' => $mappings_serialized,
                ),
                array('%d', '%s', '%s')
            );

            if ($result) {
                wp_send_json_success(array('message' => esc_html__('Form mappings saved successfully.', 'lead-magnet-for-utiliko')));
            } else {
                wp_send_json_error(array('message' => esc_html__('Failed to save form mappings.', 'lead-magnet-for-utiliko')));
            }

            $table_name = sanitize_text_field($table_name);
            $new_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM %s WHERE form_id = %d", $table_name, $form_id));
            wp_cache_set($cache_key, $new_entry, 'lead_magnet_for_utiliko', 3600);
        }
    
        if ($result === false) {
            wp_send_json_error(array('message' => esc_html__('Database error:', 'lead-magnet-for-utiliko') . ' ' . esc_html($wpdb->last_error)));
        } else {
            wp_send_json_success();
        }
    }

    function get_field_mappings() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cncrm_form_mappings');
        $cache_key = 'cncrm_field_mappings';
        $results = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');
        if ($results === false) {
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name"), ARRAY_A);

                if ($results === null) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $error = $wpdb->last_error;
                    }else {
                        $error = 'Database query failed.';
                    }
                        wp_send_json_error(array('message' => esc_html($error)));
                        return;
                }
                    wp_cache_set($cache_key, $results, 'lead_magnet_for_utiliko', 3600);
            }
    
        if ($results) {
            $mappings = array();
            foreach ($results as $row) {
                $form_id = intval($row['form_id']);
                $field_mappings = maybe_unserialize($row['mappings']);
                $mappings[$form_id] = $field_mappings;
            }
            wp_send_json_success(array('mappings' => $mappings));
        } else {
            wp_send_json_error(array('message' => 'No field mappings found.'));
        }
    }

    public function process_form_submission($fields, $entry, $form_data, $entry_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cncrm_form_mappings';
        $cache_key = 'cncrm_form_mappings_data';
        $mappings = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');

        if (false === $mappings) {
            $mappings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cncrm_form_mappings", OBJECT_K);
                wp_cache_set($cache_key, $mappings, 'lead_magnet_for_utiliko', 3600);
        }

        $mapped_data = array();
        foreach ($mappings as $mapping) {
            $field_id = $mapping->field_id;
            $mapped_field = $mapping->mapped_field;

            if (isset($fields[$field_id])) {
                $mapped_data[$mapped_field] = sanitize_text_field($fields[$field_id]);
            }
        }

    }

    public function get_wpform_fields() {
        return array(
            array('id' => 'field_1', 'label' => 'Field 1'),
            array('id' => 'field_2', 'label' => 'Field 2'),
        );
    }
    
    public function handle_wpforms_ajax_submission() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');
        $form_data = isset($_POST['form_data']) && is_array($_POST['form_data']) ? array_map('sanitize_text_field',wp_unslash($_POST['form_data'])) : array();
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    
        if (empty($form_data) || !$form_id) {
            wp_send_json_error(array('message' => __('Form data is empty.', 'lead-magnet-for-utiliko')));
            return;
        }

        $sanitized_form_data = array();
                foreach ($form_data as $key => $value) {
                    $sanitized_key = sanitize_text_field($key);
                    if (is_array($value)) {
                        $sanitized_value = array_map('sanitize_text_field', wp_unslash($value));
                    } else {
                        $sanitized_value = sanitize_text_field(wp_unslash($value));
                    }
                    $sanitized_form_data[$sanitized_key] = $sanitized_value;
                }

            if (isset($sanitized_form_data['email'])) {
                $sanitized_form_data['email'] = sanitize_email($sanitized_form_data['email']);
                if (!is_email($sanitized_form_data['email'])) {
                    wp_send_json_error(array('message' => __('Invalid email address.', 'lead-magnet-for-utiliko')));
                    return;
                }
            }
    
        $crm_url = get_option('lead-magnet-for-utiliko_crm_url');
        if (!$crm_url) {
            wp_send_json_error(array('message' => __('CRM URL is not set.', 'lead-magnet-for-utiliko')));
            return;
        }
    
        $response = $this->send_data_to_crm($crm_url, $sanitized_form_data);    
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        wp_send_json_success(array('message' => __('Data successfully sent to CRM.', 'lead-magnet-for-utiliko')));
    }

    private function send_data_to_crm($crm_url, $data) {
        $headers = array(
            'Content-Type: application/json',
            // 'Authorization: Bearer ' . $api_key,
        );

        $args = array(
            'headers' => $headers,
            'body' => wp_json_encode($data),
            'timeout' => 20,
            'sslverify' => false,
        );

        $response = wp_remote_post($crm_url, $args);
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            return array(
                'success' => false,
                'message' => 'Error sending data to CRM. Response code: ' . $response_code,
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['success']) && $data['success']) {
            return array(
                'success' => true,
                'data' => $data,
                'message' => 'Data sent successfully to CRM.',
            );
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
            return array(
                'success' => false,
                'message' => 'Error sending data to CRM: ' . $error_message,
            );
        }
    }

}

new lead_magnet_for_utiliko_Field_Mappings();
