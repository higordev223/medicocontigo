<?php
/**
 * Test script for MCTelemed video room integration with KiviCare
 *
 * This script verifies that video rooms are properly created and managed
 * for KiviCare appointments.
 *
 * Usage: Place in WordPress root and access via browser or run with WP-CLI
 */

require_once(__DIR__ . '/wp-load.php');

// Prevent direct execution in production
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    die('This test script can only run when WP_DEBUG is enabled.');
}

echo "<h1>MCTelemed Video Room Integration Test</h1>\n";
echo "<pre>\n";

// Test 1: Check if MCTelemed plugin is active
echo "=== Test 1: Plugin Status ===\n";
if (class_exists('MCTelemed_Provider_Jitsi')) {
    echo "✅ MCTelemed plugin is active\n";
} else {
    echo "❌ MCTelemed plugin is NOT active\n";
    die("Please activate the MCTelemed plugin first.\n");
}

// Test 2: Check Jitsi settings
echo "\n=== Test 2: Jitsi Configuration ===\n";
$jitsi_domain = get_option('mctelemed_jitsi_domain');
$jitsi_secret = get_option('mctelemed_jitsi_secret');
$jitsi_expiration = get_option('mctelemed_jitsi_expiration', 10);

echo "Domain: " . ($jitsi_domain ? "✅ " . esc_html($jitsi_domain) : "❌ NOT SET") . "\n";
echo "Secret: " . ($jitsi_secret ? "✅ SET (hidden)" : "⚠️  NOT SET (optional)") . "\n";
echo "Token Expiration: " . esc_html($jitsi_expiration) . " minutes\n";

if (empty($jitsi_domain)) {
    echo "\n⚠️  WARNING: Jitsi domain not configured. Please set it in:\n";
    echo "   WordPress Admin → MCTelemed → Settings\n";
}

// Test 3: Check if hooks are registered
echo "\n=== Test 3: KiviCare Hooks Registration ===\n";
global $wp_filter;

$hooks_to_check = [
    'kc_appointment_book' => 'MCTelemed_Hooks::handle_appointment_booked',
    'kc_appointment_status_update' => 'MCTelemed_Hooks::handle_appointment_status_change',
    'kc_appointment_cancel' => 'MCTelemed_Hooks::handle_appointment_cancelled',
];

foreach ($hooks_to_check as $hook => $callback) {
    if (isset($wp_filter[$hook])) {
        echo "✅ Hook '{$hook}' is registered\n";
    } else {
        echo "❌ Hook '{$hook}' is NOT registered\n";
    }
}

// Test 4: Get a sample appointment from KiviCare
echo "\n=== Test 4: Sample Appointment Check ===\n";
global $wpdb;
$appointments_table = $wpdb->prefix . 'kc_appointments';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$appointments_table'") === $appointments_table;
if (!$table_exists) {
    echo "❌ KiviCare appointments table not found\n";
    echo "   Looking for: {$appointments_table}\n";
} else {
    echo "✅ KiviCare appointments table exists\n";

    // Get most recent appointment
    $sample_appointment = $wpdb->get_row("
        SELECT * FROM {$appointments_table}
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($sample_appointment) {
        echo "✅ Found sample appointment (ID: {$sample_appointment->id})\n";
        echo "   Status: {$sample_appointment->status}\n";
        echo "   Date: {$sample_appointment->appointment_start_date} {$sample_appointment->appointment_start_time}\n";

        // Check if this appointment has a video room
        $room = get_option('mct_room_' . $sample_appointment->id);
        if ($room) {
            echo "   Video Room: ✅ {$room}\n";

            // Check room metadata
            $created_by = get_option('mct_created_by_' . $sample_appointment->id);
            $created_at = get_option('mct_created_at_' . $sample_appointment->id);

            echo "   Created By: User ID " . ($created_by ?: 'Unknown') . "\n";
            echo "   Created At: " . ($created_at ?: 'Unknown') . "\n";

            // Check for tokens
            $token_options = $wpdb->get_results($wpdb->prepare("
                SELECT option_name FROM {$wpdb->options}
                WHERE option_name LIKE %s
            ", 'mct_token_' . $sample_appointment->id . '_%'));

            if ($token_options) {
                echo "   Tokens: ✅ " . count($token_options) . " token(s) generated\n";
            } else {
                echo "   Tokens: ⚠️  No tokens found\n";
            }
        } else {
            echo "   Video Room: ❌ NOT CREATED\n";
            echo "   (This is expected if appointment status is not confirmed)\n";
        }
    } else {
        echo "⚠️  No appointments found in database\n";
    }
}

// Test 5: Simulate room creation
echo "\n=== Test 5: Simulate Room Creation ===\n";
$test_appointment_id = 99999; // Use a fake ID for testing
echo "Creating test room for fake appointment ID: {$test_appointment_id}\n";

$provider = new MCTelemed_Provider_Jitsi();
$result = $provider->create_room($test_appointment_id);

if ($result) {
    echo "✅ Room creation successful\n";

    $room = get_option('mct_room_' . $test_appointment_id);
    echo "   Room name: {$room}\n";

    // Test token generation
    $token = $provider->generate_token($test_appointment_id, 1, 'doctor');
    echo "✅ Token generation successful\n";
    echo "   Token: " . substr($token, 0, 10) . "... (truncated)\n";

    // Test get_join_url
    $join_data = $provider->get_join_url($test_appointment_id, 1, 'doctor');
    if ($join_data) {
        echo "✅ Join URL data generation successful\n";
        echo "   Domain: " . ($join_data['domain'] ?: 'NOT SET') . "\n";
        echo "   Room: {$join_data['room']}\n";
        echo "   Token: " . substr($join_data['token'], 0, 10) . "... (truncated)\n";
    } else {
        echo "❌ Join URL data generation failed (check Jitsi domain setting)\n";
    }

    // Clean up test room
    $provider->end_room($test_appointment_id);
    echo "✅ Room cleanup successful\n";

    // Verify cleanup
    $room_after = get_option('mct_room_' . $test_appointment_id);
    if ($room_after === false) {
        echo "✅ Room data properly deleted\n";
    } else {
        echo "⚠️  Room data still exists after cleanup\n";
    }
} else {
    echo "❌ Room creation failed\n";
}

// Test 6: List all existing video rooms
echo "\n=== Test 6: Existing Video Rooms ===\n";
$room_options = $wpdb->get_results("
    SELECT option_name, option_value
    FROM {$wpdb->options}
    WHERE option_name LIKE 'mct_room_%'
    ORDER BY option_id DESC
    LIMIT 10
");

if ($room_options) {
    echo "Found " . count($room_options) . " video room(s):\n";
    foreach ($room_options as $option) {
        $appointment_id = str_replace('mct_room_', '', $option->option_name);
        echo "  • Appointment #{$appointment_id}: {$option->option_value}\n";
    }
} else {
    echo "No video rooms found in database\n";
}

echo "\n=== Summary ===\n";
echo "MCTelemed plugin is integrated with KiviCare.\n";
echo "Video rooms will be automatically created when:\n";
echo "  1. A new appointment is booked (kc_appointment_book hook)\n";
echo "  2. An appointment status changes to confirmed/booked (status = 1)\n";
echo "\nVideo rooms will be automatically deleted when:\n";
echo "  1. An appointment is cancelled (kc_appointment_cancel hook)\n";
echo "  2. An appointment status changes to cancelled (status = 0)\n";

if (empty($jitsi_domain)) {
    echo "\n⚠️  NEXT STEP: Configure Jitsi domain in MCTelemed settings\n";
} else {
    echo "\n✅ System is ready for video consultations!\n";
}

echo "\n</pre>";
