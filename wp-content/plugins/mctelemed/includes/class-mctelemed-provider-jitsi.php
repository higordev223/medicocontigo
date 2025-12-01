<?php
/**
 * Basic Jitsi provider implementation for MCtelemed.
 *
 * This class creates lightweight rooms on demand and generates simple
 * JSON Web Tokens (JWT) for authentication.  Real-world deployments
 * should implement proper JWT signing using a secret shared with your
 * Jitsi server (if using lib-jitsi-meet or self‑hosted Jitsi with token
 * authentication).  Here we generate a pseudo-random string to
 * illustrate the structure.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCTelemed_Provider_Jitsi implements MCTelemed_Provider_Interface {

    /**
     * Create a video room for a given appointment.
     *
     * @param int   $appointment_id The appointment ID.
     * @param array $args Optional arguments; unused for Jitsi.
     * @return bool
     */
    public function create_room($appointment_id, $args = []) {
        // Generate a unique room name if one does not already exist.
        // Use WordPress options table since KiviCare appointments aren't WordPress posts
        $room = get_option('mct_room_' . $appointment_id);
        if (!$room) {
            $room = 'mct-' . $appointment_id . '-' . wp_generate_password(8, false, false);
            update_option('mct_room_' . $appointment_id, $room, false);
        }
        // Record creation metadata.
        update_option('mct_created_by_' . $appointment_id, get_current_user_id(), false);
        update_option('mct_created_at_' . $appointment_id, current_time('mysql'), false);
        return true;
    }

    /**
     * Generate a simple token for the user.  In a real integration,
     * implement proper JWT signing according to Jitsi requirements.
     *
     * @param int    $appointment_id The appointment ID.
     * @param int    $user_id        The WordPress user ID.
     * @param string $role           'doctor' or 'patient'.
     * @return string
     */
    public function generate_token($appointment_id, $user_id, $role) {
        // Retrieve secret and expiration from options.
        $secret     = get_option('mctelemed_jitsi_secret');
        $expiration = (int) get_option('mctelemed_jitsi_expiration', 10);
        $domain     = get_option('mctelemed_jitsi_domain');

        // For demonstration, create a fake token; in production use JWT.
        $token = wp_generate_password(32, false, false);

        // Store token in options for audit
        update_option('mct_token_' . $appointment_id . '_' . $user_id, $token, false);
        return $token;
    }

    /**
     * Get embed data for the Jitsi room.
     *
     * @param int    $appointment_id The appointment ID.
     * @param int    $user_id        The user requesting join data.
     * @param string $role           'doctor' or 'patient'.
     * @return array|false
     */
    public function get_join_url($appointment_id, $user_id, $role) {
        $room   = get_option('mct_room_' . $appointment_id);
        $domain = get_option('mctelemed_jitsi_domain');
        if (empty($room) || empty($domain)) {
            return false;
        }
        $token = $this->generate_token($appointment_id, $user_id, $role);
        // Provide data back to the caller; they can embed with external_api.js.
        return [
            'domain' => $domain,
            'room'   => $room,
            'token'  => $token,
        ];
    }

    /**
     * End the room.  For Jitsi this does not destroy anything; just
     * remove options so new rooms are created on next confirmed appointment.
     *
     * @param int $appointment_id
     * @return bool
     */
    public function end_room($appointment_id) {
        // Delete room data from options
        delete_option('mct_room_' . $appointment_id);
        delete_option('mct_created_by_' . $appointment_id);
        delete_option('mct_created_at_' . $appointment_id);

        // Remove any stored tokens - we need to search all options
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            'mct_token_' . $appointment_id . '_%'
        ));

        return true;
    }
}