<?php

function lead_magnet_for_utiliko_settings_page() {
    try {
        if (!post_type_exists('wpforms')) {
            throw new Exception('WPForms post type does not exist. Ensure WPForms is installed and activated.');
        }

        $forms = get_posts(array(
            'post_type' => 'wpforms',
            'numberposts' => -1
        ));

        if (empty($forms)) {
            throw new Exception('No WPForms found. Please create a form first.');
        }

    ?>
        <div class="wrap crmIntegationSettings">
            <h1 class="ttl_crmSettings"><?php echo esc_html__('CRM Integration Settings', 'lead-magnet-for-utiliko'); ?></h1>
            <hr class="divide">
            <div class="wp-formSelect">
                <h3 class="ttl_selectForm"><?php echo esc_html__('Select Form', 'lead-magnet-for-utiliko'); ?></h3>
            </div>
            <div class="wp-select">
                <select id="wpforms_select" name="lead_magnet_for_utiliko_selected_form" onchange="fetchFormFields(this.value)" class="select_wpform_dropdown">
                    <option value=""><?php echo esc_html__('Select Form', 'lead-magnet-for-utiliko'); ?></option>
                    <?php
                    foreach ($forms as $form) {
                        echo '<option value="' . esc_attr($form->ID) . '">' . esc_html($form->post_title) . '</option>';
                    }
                    ?>
                </select>
                <input type="hidden" name="wp-ajax-nonce" id="wp-ajax-nonce" value="<?php echo esc_attr(wp_create_nonce('wp-ajax-nonce')); ?>" />
            </div>
            <div class="wrap gs-form fieldMappingContainer">
                <div class="wp-parts">
                    <div class="card" id="wpform-gs">
                        <form method="post" id="lead-magnet-for-utiliko-settings-form">
                            <div id="inside"></div>
                            <button type="button" class="button button-primary btn_saveMapping" id="save-mapping"><?php esc_html_e('Save Mapping', 'lead-magnet-for-utiliko'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php

    } catch (Exception $e) {
        echo '<div class="error"><p>' . esc_html($e->getMessage()) . '</p></div>';
    }
}

add_action('admin_enqueue_scripts', 'lead_magnet_for_utiliko_admin_enqueue_scripts');
function lead_magnet_for_utiliko_admin_enqueue_scripts() {
    wp_enqueue_script('lead-magnet-for-utiliko-script', plugins_url('assets/js/admin-script.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('lead-magnet-for-utiliko-script', 'lead_magnet_for_utiliko_admin_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('lead_magnet_for_utiliko_nonce')
    ));
}