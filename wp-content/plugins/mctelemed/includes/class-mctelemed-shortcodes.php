<?php
/**
 * Registers and implements shortcodes for the MCtelemed plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCTelemed_Shortcodes {
    /**
     * Register shortcodes with WordPress.
     */
    public function register_shortcodes() {
        add_shortcode('mctelemed', [$this, 'render_mctelemed']);
        add_shortcode('mct_next_meeting', [$this, 'render_next_meeting']);
    }

    /**
     * Instantiate the configured provider class.
     *
     * @return MCTelemed_Provider_Interface
     */
    protected function get_provider() {
        $provider = get_option('mctelemed_provider', 'jitsi');
        switch ($provider) {
            case 'jitsi':
                return new MCTelemed_Provider_Jitsi();
            // Future providers can be added here.
            // case 'zoom': return new MCTelemed_Provider_Zoom();
            // case 'meet': return new MCTelemed_Provider_Meet();
            default:
                return new MCTelemed_Provider_Jitsi();
        }
    }

    /**
     * Render the teleconsultation embed for a given appointment.
     *
     * Usage: [mctelemed appt="123"] or [mctelemed] with ?appt=123 in URL.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_mctelemed($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to join the consultation.', 'mctelemed') . '</p>';
        }

        $atts = shortcode_atts(['appt' => 0], $atts, 'mctelemed');
        $appointment_id = absint($atts['appt']);
        if (!$appointment_id && isset($_GET['appt'])) {
            $appointment_id = absint($_GET['appt']);
        }
        if (!$appointment_id) {
            return '<p>' . esc_html__('No appointment specified.', 'mctelemed') . '</p>';
        }
        $user_id = get_current_user_id();

        // Determine role based on appointment metadata.
        // NOTE: In production, integrate with KiviCare to fetch doctor/patient.
        $role = 'guest';
        if (current_user_can('kc_doctor')) {
            $role = 'doctor';
        } elseif (current_user_can('kc_patient')) {
            $role = 'patient';
        }

        $provider = $this->get_provider();
        // Create the room if it doesn't exist yet.
        $provider->create_room($appointment_id);
        $join_data = $provider->get_join_url($appointment_id, $user_id, $role);

        if (!$join_data || !isset($join_data['domain'])) {
            return '<p>' . esc_html__('Unable to start teleconsultation: provider configuration is incomplete.', 'mctelemed') . '</p>';
        }

        // Retrieve branding options.
        $title = esc_html(get_option('mctelemed_branding_title', 'Videoconsulta'));
        $logo  = esc_url(get_option('mctelemed_branding_logo', ''));

        // Prepare HTML wrapper.  The iframe will be initialised via external_api.js.
        ob_start();
        ?>
        <div class="mctelemed-container">
            <?php if ($logo) : ?>
                <img src="<?php echo $logo; ?>" alt="logo" class="mctelemed-logo" style="max-width:200px;margin-bottom:10px;" />
            <?php endif; ?>
            <h2 class="mctelemed-heading"><?php echo $title; ?></h2>
            <div id="mctelemed-video" style="width:100%;height:500px;background:#000;"></div>
        </div>
        <script type="text/javascript">
        // Load the Jitsi external API script if not already loaded.
        (function() {
            function initJitsi() {
                const domain = <?php echo wp_json_encode($join_data['domain']); ?>;
                const roomName = <?php echo wp_json_encode($join_data['room']); ?>;
                const token = <?php echo wp_json_encode($join_data['token']); ?>;
                const parentNode = document.getElementById('mctelemed-video');
                if (!parentNode) return;
                const options = {
                    roomName: roomName,
                    parentNode: parentNode,
                    jwt: token,
                    userInfo: {
                        displayName: <?php echo wp_json_encode(wp_get_current_user()->display_name); ?>
                    },
                    configOverwrite: {
                        // Example overrides: start muted to prevent audio feedback.
                        startWithAudioMuted: true,
                        startWithVideoMuted: false,
                    },
                    interfaceConfigOverwrite: {
                        // Hide the Jitsi watermark for a more native feel.
                        SHOW_JITSI_WATERMARK: false,
                    }
                };
                try {
                    new JitsiMeetExternalAPI(domain, options);
                } catch (e) {
                    console.error(e);
                }
            }
            // Load script if not present.
            if (typeof JitsiMeetExternalAPI === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://' + <?php echo wp_json_encode($join_data['domain']); ?> + '/external_api.js';
                script.onload = initJitsi;
                document.body.appendChild(script);
            } else {
                initJitsi();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the next meeting info for the logged in user.
     *
     * This is a placeholder implementation.  Integrate with KiviCare
     * appointment data to fetch the next confirmed appointment.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_next_meeting($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your upcoming appointments.', 'mctelemed') . '</p>';
        }
        // TODO: Query KiviCare for the next confirmed appointment for current user.
        return '<p>' . esc_html__('Next teleconsultation will appear here once scheduled.', 'mctelemed') . '</p>';
    }
}