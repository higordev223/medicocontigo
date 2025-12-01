<?php
/*
 * Plugin Name: MCtelemed
 * Plugin URI:  https://www.medicocontigo.com
 * Description: Telemedicine integration for KiviCare. Provides embedded video consultations that are triggered when a patient books an appointment. Compatible with WooCommerce and KiviCare Pro.
 * Version:     0.1.0
 * Author:      Louali Salem Douh
 * Author URI:  https://www.medicocontigo.com
 * License:     GPL2
 *
 * The plugin is structured to be easily extended and customised.  It defines
 * shortcodes for embedding a teleconsultation room and displaying the next
 * appointment, registers an admin settings page for choosing the video
 * provider and configuring API keys, and exposes hooks that tie into
 * WooCommerce and KiviCare to create and destroy virtual rooms.
 */

// Abort if this file is called directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('MCTELEMED_VERSION', '0.1.0');
define('MCTELEMED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCTELEMED_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes within the plugin.
require_once MCTELEMED_PLUGIN_DIR . 'includes/class-mctelemed-provider-interface.php';
require_once MCTELEMED_PLUGIN_DIR . 'includes/class-mctelemed-provider-jitsi.php';
require_once MCTELEMED_PLUGIN_DIR . 'includes/class-mctelemed-settings.php';
require_once MCTELEMED_PLUGIN_DIR . 'includes/class-mctelemed-shortcodes.php';
require_once MCTELEMED_PLUGIN_DIR . 'includes/class-mctelemed-hooks.php';

/**
 * Main initialisation function.  Hooks are registered when plugins are loaded.
 */
function mctelemed_init() {
    // Register settings page.
    $settings = new MCTelemed_Settings();
    add_action('admin_menu', [$settings, 'register_menu']);
    add_action('admin_init', [$settings, 'register_settings']);

    // Register shortcodes.
    $shortcodes = new MCTelemed_Shortcodes();
    add_action('init', [$shortcodes, 'register_shortcodes']);

    // Register hooks for WooCommerce and KiviCare.
    $hooks = new MCTelemed_Hooks();
    add_action('woocommerce_payment_complete', [$hooks, 'handle_payment_complete'], 10, 1);

    // KiviCare appointment hooks - automatically create/destroy video rooms
    add_action('kc_appointment_book', [$hooks, 'handle_appointment_booked'], 10, 1);
    add_action('kc_appointment_status_update', [$hooks, 'handle_appointment_status_change'], 10, 2);
    add_action('kc_appointment_cancel', [$hooks, 'handle_appointment_cancelled'], 10, 1);
}
add_action('plugins_loaded', 'mctelemed_init');

/**
 * Activation hook.  Sets sensible defaults for plugin options.
 */
function mctelemed_activate() {
    // Only set defaults if they have not already been saved.
    $defaults = [
        'provider'          => 'jitsi',
        'jitsi_domain'      => '',
        'jitsi_secret'      => '',
        'jitsi_expiration'  => 10, // minutes
        'access_window'     => 15, // minutes before and after appointment
        'branding_title'    => 'Videoconsulta',
        'branding_logo'     => '',
    ];
    foreach ($defaults as $key => $value) {
        $option_name = 'mctelemed_' . $key;
        if (false === get_option($option_name)) {
            add_option($option_name, $value);
        }
    }
}
register_activation_hook(__FILE__, 'mctelemed_activate');

/**
 * Deactivation hook.  Currently a no‑op, but available for future cleanup.
 */
function mctelemed_deactivate() {
    // Intentionally left blank.  Could be used to remove scheduled tasks.
}
register_deactivation_hook(__FILE__, 'mctelemed_deactivate');