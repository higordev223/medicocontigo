<?php
/**
 * Defines the interface that all video providers must implement.
 *
 * Implementations should take care of creating rooms, generating secure
 * access tokens for users based on appointment metadata, retrieving join
 * URLs and cleaning up rooms.  All provider classes should implement
 * `create_room`, `generate_token`, `get_join_url` and `end_room`.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface MCTelemed_Provider_Interface {
    /**
     * Create a video room for a given appointment.
     *
     * @param int $appointment_id The appointment ID.
     * @param array $args Optional provider specific arguments.
     * @return bool True on success, false on failure.
     */
    public function create_room($appointment_id, $args = []);

    /**
     * Generate a secure token or auth payload for a user to join a room.
     *
     * @param int    $appointment_id The appointment ID.
     * @param int    $user_id        The WordPress user ID of the participant.
     * @param string $role           Either 'doctor' or 'patient'.
     * @return string|false A token string on success or false on error.
     */
    public function generate_token($appointment_id, $user_id, $role);

    /**
     * Get a URL or embeddable HTML snippet for joining the room.
     *
     * Providers can either return a full URL to open in a new window or
     * embed code that can be placed inside an iframe.  For providers that
     * rely on external API scripts (e.g. Jitsi), this method may return
     * an array containing both the URL and any necessary configuration.
     *
     * @param int    $appointment_id The appointment ID.
     * @param int    $user_id        The WordPress user ID of the participant.
     * @param string $role           Either 'doctor' or 'patient'.
     * @return mixed String URL or array with embed data, or false on error.
     */
    public function get_join_url($appointment_id, $user_id, $role);

    /**
     * End an existing room and clean up any related resources.
     *
     * @param int $appointment_id The appointment ID.
     * @return bool True on success, false on failure.
     */
    public function end_room($appointment_id);
}