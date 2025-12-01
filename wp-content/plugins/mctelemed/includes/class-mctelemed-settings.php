<?php
/**
 * Handles registration and rendering of the MCtelemed settings page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCTelemed_Settings {
    /**
     * Register a settings page under the Settings menu.
     */
    public function register_menu() {
        add_options_page(
            __('MCtelemed Settings', 'mctelemed'),
            __('MCtelemed', 'mctelemed'),
            'manage_options',
            'mctelemed',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings and fields.
     */
    public function register_settings() {
        // Register each option individually.
        register_setting('mctelemed_options', 'mctelemed_provider');
        register_setting('mctelemed_options', 'mctelemed_jitsi_domain');
        register_setting('mctelemed_options', 'mctelemed_jitsi_secret');
        register_setting('mctelemed_options', 'mctelemed_jitsi_expiration');
        register_setting('mctelemed_options', 'mctelemed_access_window');
        register_setting('mctelemed_options', 'mctelemed_branding_title');
        register_setting('mctelemed_options', 'mctelemed_branding_logo');

        // Settings section: Provider.
        add_settings_section(
            'mctelemed_section_provider',
            __('Video Provider', 'mctelemed'),
            [$this, 'section_provider_desc'],
            'mctelemed'
        );
        add_settings_field(
            'mctelemed_provider',
            __('Active Provider', 'mctelemed'),
            [$this, 'field_provider'],
            'mctelemed',
            'mctelemed_section_provider'
        );
        add_settings_field(
            'mctelemed_jitsi_domain',
            __('Jitsi Domain', 'mctelemed'),
            [$this, 'field_jitsi_domain'],
            'mctelemed',
            'mctelemed_section_provider'
        );
        add_settings_field(
            'mctelemed_jitsi_secret',
            __('Jitsi Secret', 'mctelemed'),
            [$this, 'field_jitsi_secret'],
            'mctelemed',
            'mctelemed_section_provider'
        );
        add_settings_field(
            'mctelemed_jitsi_expiration',
            __('Token Expiration (minutes)', 'mctelemed'),
            [$this, 'field_jitsi_expiration'],
            'mctelemed',
            'mctelemed_section_provider'
        );

        // Settings section: Access window & branding.
        add_settings_section(
            'mctelemed_section_access',
            __('Access & Branding', 'mctelemed'),
            [$this, 'section_access_desc'],
            'mctelemed'
        );
        add_settings_field(
            'mctelemed_access_window',
            __('Access Window (minutes)', 'mctelemed'),
            [$this, 'field_access_window'],
            'mctelemed',
            'mctelemed_section_access'
        );
        add_settings_field(
            'mctelemed_branding_title',
            __('Title', 'mctelemed'),
            [$this, 'field_branding_title'],
            'mctelemed',
            'mctelemed_section_access'
        );
        add_settings_field(
            'mctelemed_branding_logo',
            __('Logo URL', 'mctelemed'),
            [$this, 'field_branding_logo'],
            'mctelemed',
            'mctelemed_section_access'
        );
    }

    /**
     * Section description for provider settings.
     */
    public function section_provider_desc() {
        echo '<p>' . esc_html__('Select the video provider and configure connection settings.', 'mctelemed') . '</p>';
    }

    /**
     * Section description for access window & branding.
     */
    public function section_access_desc() {
        echo '<p>' . esc_html__('Configure how long before/after an appointment users can join and customise the display title and logo.', 'mctelemed') . '</p>';
    }

    /**
     * Provider selection field.
     */
    public function field_provider() {
        $provider = get_option('mctelemed_provider', 'jitsi');
        ?>
        <select name="mctelemed_provider" id="mctelemed_provider">
            <option value="jitsi" <?php selected('jitsi', $provider); ?>><?php esc_html_e('Jitsi', 'mctelemed'); ?></option>
            <option value="zoom" <?php selected('zoom', $provider); ?>><?php esc_html_e('Zoom', 'mctelemed'); ?></option>
            <option value="meet" <?php selected('meet', $provider); ?>><?php esc_html_e('Google Meet', 'mctelemed'); ?></option>
        </select>
        <?php
    }

    /**
     * Jitsi domain field.
     */
    public function field_jitsi_domain() {
        $value = esc_attr(get_option('mctelemed_jitsi_domain', ''));
        echo '<input type="text" name="mctelemed_jitsi_domain" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter your Jitsi domain (e.g. meet.example.com).', 'mctelemed') . '</p>';
    }

    /**
     * Jitsi secret field.
     */
    public function field_jitsi_secret() {
        $value = esc_attr(get_option('mctelemed_jitsi_secret', ''));
        echo '<input type="text" name="mctelemed_jitsi_secret" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('If your Jitsi deployment uses token authentication, provide the shared secret here.', 'mctelemed') . '</p>';
    }

    /**
     * Jitsi expiration field.
     */
    public function field_jitsi_expiration() {
        $value = intval(get_option('mctelemed_jitsi_expiration', 10));
        echo '<input type="number" name="mctelemed_jitsi_expiration" value="' . $value . '" class="small-text" min="1" />';
        echo '<p class="description">' . esc_html__('Number of minutes tokens remain valid.', 'mctelemed') . '</p>';
    }

    /**
     * Access window field.
     */
    public function field_access_window() {
        $value = intval(get_option('mctelemed_access_window', 15));
        echo '<input type="number" name="mctelemed_access_window" value="' . $value . '" class="small-text" min="0" />';
        echo '<p class="description">' . esc_html__('Minutes before and after appointment during which users can join.', 'mctelemed') . '</p>';
    }

    /**
     * Branding title field.
     */
    public function field_branding_title() {
        $value = esc_attr(get_option('mctelemed_branding_title', 'Videoconsulta'));
        echo '<input type="text" name="mctelemed_branding_title" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Displayed as the heading on the teleconsultation page.', 'mctelemed') . '</p>';
    }

    /**
     * Branding logo field.
     */
    public function field_branding_logo() {
        $value = esc_attr(get_option('mctelemed_branding_logo', ''));
        echo '<input type="text" name="mctelemed_branding_logo" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter the URL of a logo to display above the video.', 'mctelemed') . '</p>';
    }

    /**
     * Render the settings page content.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MCtelemed Settings', 'mctelemed'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mctelemed_options');
                do_settings_sections('mctelemed');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}