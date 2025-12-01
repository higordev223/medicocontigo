<?php
/**
 * Contains integration hooks for WooCommerce and KiviCare.
 *
 * The MCtelemed plugin listens to WooCommerce payment completion events to
 * create telemedicine rooms after a successful order, and provides stubs
 * for tying into KiviCare appointment status changes.  Actual
 * integration with KiviCare should be implemented by reading its
 * documentation or source code to locate appropriate actions and filters.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCTelemed_Hooks {
    /**
     * Handle WooCommerce payment completion.
     *
     * @param int $order_id The ID of the completed WooCommerce order.
     */
    public function handle_payment_complete($order_id) {
        // Retrieve the order object.
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        // Loop through order items and attempt to find appointment IDs.
        foreach ($order->get_items() as $item) {
            // Custom logic: if an item meta contains 'kc_appointment_id', treat it as appointment.
            $appointment_id = $item->get_meta('kc_appointment_id');
            if ($appointment_id) {
                $appointment_id = absint($appointment_id);
                $this->create_room_for_appointment($appointment_id);
            }
        }
    }

    /**
     * Handle KiviCare appointment booking (when appointment is created).
     * Hook: kc_appointment_book
     *
     * @param int $appointment_id The KiviCare appointment ID.
     */
    public function handle_appointment_booked($appointment_id) {
        if (empty($appointment_id)) {
            return;
        }

        // Create video room for booked appointment
        $this->create_room_for_appointment($appointment_id);

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MCTelemed: Video room created for appointment #{$appointment_id}");
        }
    }

    /**
     * Handle KiviCare appointment status updates.
     * Hook: kc_appointment_status_update
     *
     * @param int    $appointment_id The KiviCare appointment ID.
     * @param string $status         The new status (1=booked, 0=cancelled, 3=checkout, 4=checkin).
     */
    public function handle_appointment_status_change($appointment_id, $status) {
        if (empty($appointment_id)) {
            return;
        }

        // Status: 1 = booked/confirmed
        if ($status == '1' || $status == 1) {
            $this->create_room_for_appointment($appointment_id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MCTelemed: Video room created for confirmed appointment #{$appointment_id}");
            }
        }
        // Status: 0 = cancelled
        elseif ($status == '0' || $status == 0) {
            $this->end_room_for_appointment($appointment_id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MCTelemed: Video room ended for cancelled appointment #{$appointment_id}");
            }
        }
    }

    /**
     * Handle KiviCare appointment cancellation.
     * Hook: kc_appointment_cancel
     *
     * @param int $appointment_id The KiviCare appointment ID.
     */
    public function handle_appointment_cancelled($appointment_id) {
        if (empty($appointment_id)) {
            return;
        }

        $this->end_room_for_appointment($appointment_id);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MCTelemed: Video room ended for appointment #{$appointment_id}");
        }
    }

    /**
     * Create a telemedicine room for a KiviCare appointment.
     *
     * @param int $appointment_id The KiviCare appointment ID.
     */
    protected function create_room_for_appointment($appointment_id) {
        $provider = $this->get_provider();
        if ($provider) {
            $provider->create_room($appointment_id);
            // Optionally set join window metadata here.
        }
    }

    /**
     * End a telemedicine room for a KiviCare appointment.
     *
     * @param int $appointment_id The KiviCare appointment ID.
     */
    protected function end_room_for_appointment($appointment_id) {
        $provider = $this->get_provider();
        if ($provider) {
            $provider->end_room($appointment_id);
        }
    }

    /**
     * Get provider instance via shortcodes class helper.
     *
     * @return MCTelemed_Provider_Interface
     */
    protected function get_provider() {
        $shortcodes = new MCTelemed_Shortcodes();
        return call_user_func([
            $shortcodes,
            'get_provider'
        ]);
    }
}