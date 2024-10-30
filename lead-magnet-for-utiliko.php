<?php
/*
Plugin Name: Lead Magnet For Utiliko
Plugin URI: https://codengine.co/
Description: Integrates WPForms submissions with a custom CRM. This plugin helps you to generate your WPForms leads into your custom CRM.
Version: 1.1.5
Author: CodeNgine Technologies
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: lead-magnet-for-utiliko
*/

if (!defined('ABSPATH')) { exit; }

define('LEAD_MAGNET_FOR_UTILIKO_VERSION', '1.0');
define('LEAD_MAGNET_FOR_UTILIKO_DIR', plugin_dir_path(__FILE__));
define('LEAD_MAGNET_FOR_UTILIKO_URL', plugin_dir_url(__FILE__));
define('LEAD_MAGNET_FOR_UTILIKO_BASE_FILE', plugin_basename(__FILE__));
define('LEAD_MAGNET_FOR_UTILIKO_ROOT', dirname(__FILE__));
define('LEAD_MAGNET_FOR_UTILIKO_DB_VERSION', '1.0');
define('LEAD_MAGNET_FOR_UTILIKO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LEAD_MAGNET_FOR_UTILIKO_CURRENT_THEME', get_stylesheet_directory());

require_once LEAD_MAGNET_FOR_UTILIKO_PLUGIN_PATH . 'includes/lead-magnet-for-utiliko-integration-ui.php';
require_once LEAD_MAGNET_FOR_UTILIKO_PLUGIN_PATH . 'includes/lead-magnet-for-utiliko-settings-page.php';
require_once LEAD_MAGNET_FOR_UTILIKO_PLUGIN_PATH . 'includes/lead-magnet-for-utiliko-field-mappings.php';

class LEAD_MAGNET_FOR_UTILIKO_Init {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_wpform_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'lead_magnet_for_utiliko_enqueue_admin_scripts'));
        add_action('wp_ajax_get_crm_access_token_ajax', array($this, 'get_crm_access_token_ajax'));
        add_action('wp_ajax_nopriv_get_crm_access_token_ajax', array($this, 'get_crm_access_token_ajax'));
        add_action('wpforms_process_complete', array($this, 'process_wpforms_entry'), 10, 4);
        add_action('wp_ajax_fetch_wpform_fields', array($this, 'fetch_wpform_fields'));
        add_action('wp_ajax_nopriv_fetch_wpform_fields', array($this, 'fetch_wpform_fields'));
        add_action('init', array($this, 'load_css_and_js_files'));
        add_action('wp_ajax_saveCRM_access_token', 'saveCRM_access_token');
        add_action('wp_ajax_saveAccess_token_ajax', 'saveAccess_token_ajax');
        add_action('wp_ajax_nopriv_saveAccess_token_ajax', 'saveAccess_token_ajax');
        
        register_activation_hook(__FILE__, array($this, 'lead_magnet_for_utiliko_activate'));
        register_deactivation_hook(__FILE__, array($this, 'lead_magnet_for_utiliko_deactivate'));
        register_uninstall_hook(__FILE__, array('LEAD_MAGNET_FOR_UTILIKO_Init', 'lead_magnet_for_utiliko_uninstall'));

        add_action('plugins_loaded', array($this, 'lead_magnet_for_utiliko_create_plugin_tables'));
        add_action('plugins_loaded', array($this,'lead_magnet_for_utiliko_alter_plugin_tables'));
        add_action('plugins_loaded', array($this,'lead_magnet_for_utiliko_alter_cncrm_entries_table'));

        add_filter('plugin_action_links_' . LEAD_MAGNET_FOR_UTILIKO_BASE_FILE, array($this, 'lead_magnet_for_utiliko_plugin_action_links'));
        add_filter('gettext', array($this, 'change_button_text'), 20, 3);
    }

    public function is_wpforms_installed_and_activated() {
        if ( ! class_exists( 'WPForms' ) ) { return false; }
        if ( ! defined( 'WPFORMS_VERSION' ) ) { return false; }
        return true;
    }

    public function register_settings() {
        register_setting(
            'lead_magnet_for_utiliko_options_group',
            'lead-magnet-for-utiliko_crm_url',
            array(
                'sanitize_callback' => array($this, 'sanitize_crm_url')
            )
        );

        add_settings_section('lead-magnet-for-utiliko_section', '', null, 'lead-magnet-for-utiliko-config');
        add_settings_field(
            'lead-magnet-for-utiliko_crm_url',
            'CRM URL',
            array($this, 'crm_url_field'),
            'lead-magnet-for-utiliko-config',
            'lead-magnet-for-utiliko_section'
        );
    }

    public function sanitize_crm_url($input) { return esc_url_raw($input); }

    public function change_button_text($translated_text, $domain) {
        return ('Save Changes' === $translated_text && 'default' === $domain) ? 'Send Data' : $translated_text;
    }

    public function crm_url_field() {
        $crm_url = get_option('lead-magnet-for-utiliko_crm_url');
        echo '<input type="text" name="lead-magnet-for-utiliko_crm_url" value="' . esc_attr($crm_url) . '" class="regular-text">';
    }

    public function register_wpform_menu_pages() {
        add_menu_page(
            __('WPForms Utiliko LeadSync Integration', 'lead-magnet-for-utiliko'),
            'Lead Magnet For Utiliko',
            'manage_options',
            'wpforms-crm-integration',
            'lead_magnet_for_utiliko_render_integration_ui',
            'dashicons-admin-generic',
            6
        );
        add_submenu_page(
            'wpforms-crm-integration',
            'CRM Settings',
            'Field Mapping',
            'manage_options',
            'utiliko-field-mapping',
            'lead_magnet_for_utiliko_settings_page',
        );
    }

    function lead_magnet_for_utiliko_enqueue_admin_scripts($hook) {

        $is_lead_magnet_for_utiliko_settings_page = strpos($hook, 'lead-magnet-for-utiliko-settings') !== false;
        if (!$is_lead_magnet_for_utiliko_settings_page) { return; }
    
        wp_enqueue_script('jquery');
        if (class_exists('WPForms')) {
            wp_enqueue_script('wpforms-admin-script', WPFORMS_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), WPFORMS_VERSION, true);
        }
    
        wp_enqueue_script('lead-magnet-for-utiliko-script', LEAD_MAGNET_FOR_UTILIKO_URL . 'assets/js/admin-script.js', array('jquery', 'wpforms-admin-script'), '1.0', true);    
        wp_localize_script('lead-magnet-for-utiliko-script', 'lead_magnet_for_utiliko_admin_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lead_magnet_for_utiliko_nonce'),
        ));
        wp_enqueue_style('admin-style', LEAD_MAGNET_FOR_UTILIKO_URL . 'assets/css/admin-style.css', array(), LEAD_MAGNET_FOR_UTILIKO_VERSION, 'all');
    }

    public function lead_magnet_for_utiliko_activate() {
        if (get_option('lead-magnet-for-utiliko_crm_url') === false) {
            update_option('lead-magnet-for-utiliko_crm_url', '');
        }
        $this->lead_magnet_for_utiliko_create_plugin_tables();
        $this->lead_magnet_for_utiliko_alter_plugin_tables();
        update_option('lead_magnet_for_utiliko_db_version', LEAD_MAGNET_FOR_UTILIKO_DB_VERSION);
    }

    function lead_magnet_for_utiliko_alter_cncrm_entries_table() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cncrm_entries');
        $cache_key = 'lead_magnet_for_utiliko_cncrm_entries_table_schema';
        $schema_version = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');

        if ($schema_version !== LEAD_MAGNET_FOR_UTILIKO_DB_VERSION) {
            $alter_entries_query = "ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS wpform_entry_id INT(11) DEFAULT 0, ADD COLUMN IF NOT EXISTS crm_url VARCHAR(255) NOT NULL, ADD COLUMN IF NOT EXISTS crm_token VARCHAR(255) NOT NULL, ADD COLUMN IF NOT EXISTS entry_data TEXT DEFAULT '';";
            $wpdb->query($alter_entries_query);
            wp_cache_set($cache_key, LEAD_MAGNET_FOR_UTILIKO_DB_VERSION, 'lead_magnet_for_utiliko');
        }
    }

    function lead_magnet_for_utiliko_alter_plugin_tables() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cncrm_form_mappings');
        $cache_key = 'lead_magnet_for_utiliko_form_mappings_table_schema';
        $schema_version = wp_cache_get($cache_key, 'lead_magnet_for_utiliko');

        if ($schema_version !== LEAD_MAGNET_FOR_UTILIKO_DB_VERSION) {
            $alter_mapping_table = "ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS mappings TEXT NOT NULL, ADD COLUMN IF NOT EXISTS form_id INT NOT NULL, ADD COLUMN IF NOT EXISTS form_name VARCHAR(255) NOT NULL, ADD COLUMN IF NOT EXISTS crm_url VARCHAR(255) NOT NULL, ADD COLUMN IF NOT EXISTS crm_token VARCHAR(255) NOT NULL;";
            $wpdb->query($alter_mapping_table);
            wp_cache_set($cache_key, LEAD_MAGNET_FOR_UTILIKO_DB_VERSION, 'lead_magnet_for_utiliko');
        }
    }

    function lead_magnet_for_utiliko_create_plugin_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $form_mappings_table = esc_sql($wpdb->prefix . 'cncrm_form_mappings');
        $entries_table = esc_sql($wpdb->prefix . 'cncrm_entries');
        $sql_form_mappings = "CREATE TABLE IF NOT EXISTS `$form_mappings_table` (
            id INT NOT NULL AUTO_INCREMENT,
            form_id INT NOT NULL,
            wpform_id INT NOT NULL,
            crm_field VARCHAR(255) NOT NULL,
            wpform_field VARCHAR(255) NOT NULL,
            form_name VARCHAR(255) NOT NULL,
            mappings TEXT NOT NULL,
            crm_url VARCHAR(255) NOT NULL,
            crm_token VARCHAR(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_entries = "CREATE TABLE IF NOT EXISTS `$entries_table` (
            id INT NOT NULL AUTO_INCREMENT,
            wpform_entry_id INT NOT NULL,
            entry_data TEXT NOT NULL,
            crm_url VARCHAR(255) NOT NULL,
            crm_token VARCHAR(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_form_mappings);
        dbDelta($sql_entries);
    }

    public function lead_magnet_for_utiliko_deactivate() {
        wp_clear_scheduled_hook('lead-magnet-for-utiliko_cron_hook');
        delete_transient('lead-magnet-for-utiliko_temp_data');
    }

    public static function lead_magnet_for_utiliko_uninstall() {
        if (defined('WP_UNINSTALL_PLUGIN')) {

            global $wpdb;
            $tables = array('cncrm_form_mappings', 'cncrm_entries', 'cncrm_error_log');
            
            foreach ($tables as $table) {
                $table_name = $wpdb->prefix . $table;
                wp_cache_delete($table_name, 'lead_magnet_for_utiliko');
                $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS {$table_name}"));
            }
            delete_option('lead-magnet-for-utiliko_crm_url');
            delete_option('lead_magnet_for_utiliko_db_version');
        }
    }

    public function lead_magnet_for_utiliko_plugin_action_links($links) {
        $settings_link = '<a href="admin.php?page=lead-magnet-for-utiliko-settings">' . __('Settings', 'lead-magnet-for-utiliko') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    function saveAccess_token_ajax() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');
        if (isset($_POST['token'])) {
            $access_token = sanitize_text_field(wp_unslash($_POST['token']));
            update_option('lead_magnet_for_utiliko_crm_access_token', $access_token);
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Token not provided.'));
        }
    }

    public function load_css_and_js_files() {
        wp_enqueue_style('lead-magnet-for-utiliko-style', LEAD_MAGNET_FOR_UTILIKO_URL . 'assets/css/admin-style.css', array(), LEAD_MAGNET_FOR_UTILIKO_VERSION, 'all');
        wp_enqueue_script('lead-magnet-for-utiliko-script', LEAD_MAGNET_FOR_UTILIKO_URL . 'assets/js/admin-script.js', array('jquery'), LEAD_MAGNET_FOR_UTILIKO_VERSION, true);
        wp_localize_script('lead-magnet-for-utiliko-script', 'lead_magnet_for_utiliko_admin_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lead_magnet_for_utiliko_nonce'),
        ));
    }

    public function process_wpforms_entry($fields, $entry, $form_data, $entry_id) {
        $crm_url = get_option('lead-magnet-for-utiliko_crm_url');
            if (empty($crm_url)) { return; }
            $data = array();
            foreach ($fields as $field_id => $field) {
                    $data[$field['name']] = $field['value'];
                }
            $response = wp_remote_post($crm_url, array(
                    'method' => 'POST',
                    'body' => $data,
                ));
            if (is_wp_error($response)) {
                    $this->log_error($response->get_error_message());
                }
    }

    public function get_crm_access_token_ajax() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');
        global $wpdb;
        $table_name = $wpdb->prefix . 'cncrm_entries';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 1;
        $cache_key = 'crm_access_token_' . $id;
        $cache_group = 'lead_magnet_for_utiliko';

        $cached_result = wp_cache_get($cache_key, $cache_group);

        if ($cached_result === false) {
            $result = $wpdb->get_row($wpdb->prepare("SELECT crm_token, form_id FROM $table_name WHERE id = %d", $id));
    
            if (!empty($result)) {
                wp_cache_set($cache_key, $result, $cache_group, 3600);
            }
        } else {
            $result = $cached_result;
        }

        if (empty($result) || empty($result->crm_token)) {
            wp_send_json_error(array('message' => 'CRM Access Token not found.'));
        } else {
            wp_send_json_success(array(
                'access_token' => $result->crm_token,
                'form_id' => $result->form_id
            ));
        }
        wp_die();
    }

    function saveCRM_access_token() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        $access_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';
        if (empty($access_token)) {
            wp_send_json_error(array('message' => 'Invalid access token'));
        }

        if (update_option('lead_magnet_for_utiliko_crm_access_token', $access_token)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to save access token'));
        }
    }

    public function fetch_wpform_fields() {
        check_ajax_referer('lead_magnet_for_utiliko_nonce', 'nonce');
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(array('message' => __('Invalid form ID.', 'lead-magnet-for-utiliko')));
            return;
        }
    
        $form = wpforms()->form->get($form_id);
        if (!$form) {
            wp_send_json_error(array('message' => __('Form not found.', 'lead-magnet-for-utiliko')));
            return;
        }
    
        $fields = !empty($form->post_content) ? json_decode($form->post_content, true) : array();
        if (empty($fields['fields'])) {
            wp_send_json_error(array('message' => __('No fields found for this form.', 'lead-magnet-for-utiliko')));
            return;
        }
        ob_start();
        foreach ($fields['fields'] as $field) {
            ?>
            <div class="form-field-mapping div-form-label">
                <label for="field_mapping_<?php echo esc_attr($field['id']); ?>" class="lbl_field_names">
                    <?php echo esc_html($field['label']); ?>
                </label>
                <select name="lead_magnet_for_utiliko_field_mappings[<?php echo esc_attr($field['id']); ?>]" id="field_mapping_<?php echo esc_attr($field['id']); ?>" class="lead_magnet_for_utiliko_select_dropdown">
                    <option value=""><?php echo esc_html__('Select Mapping', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="leadName" title="Use Lead Name as per your CRM Lead Name column."><?php echo esc_html__('Lead Name', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="firstName" title="Use First Name as per your CRM First Name column."><?php echo esc_html__('First Name', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="lastName" title="Use Last Name as per your CRM Last Name column."><?php echo esc_html__('Last Name', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="businessEmail" title="Use Business Email as per your CRM Business Email column."><?php echo esc_html__('Business Email', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="companyName" title="Use Company Name as per your CRM Company Name column."><?php echo esc_html__('Company Name', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="companyPhone" title="Use Company Phone as per your CRM Company Phone column."><?php echo esc_html__('Company Phone', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="phone" title="Use Phone as per your CRM Phone column."><?php echo esc_html__('Phone', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="email" title="Use Email as per your CRM Email column."><?php echo esc_html__('Email', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="location" title="Use Location as per your CRM Location column."><?php echo esc_html__('Location', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="address" title="Use Address as per your CRM Address column."><?php echo esc_html__('Address', 'lead-magnet-for-utiliko'); ?></option>
                    <option value="message" title="Use Message as per your CRM Message column."><?php echo esc_html__('Message', 'lead-magnet-for-utiliko'); ?></option>
                </select>
            </div>
            <?php
        }
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

}

new LEAD_MAGNET_FOR_UTILIKO_Init();
