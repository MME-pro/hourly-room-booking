<?php
/**
 * Daily Summary Class
 *
 * Sends a scheduled summary of one day's figures — bookings, per-room usage
 * and revenue — to a configurable list of addresses.
 *
 * The summary covers the bookings that were *created* on the day, whatever
 * date they are for: a booking taken on the 4th for the 12th belongs to the
 * 4th's summary, together with its value. Money received is the exception —
 * that is counted on the day it actually came in.
 *
 * Which calendar day is reported is the one that ended at the configured send
 * time. At the default 00:00 that is the day that just finished; set it to
 * 18:00 and the mail covers the current day up to that point. See
 * resolve_summary_date().
 *
 * The mail is rendered from the branded "daily_summary_admin" email template,
 * so its wording and layout are editable on the Email Templates screen like
 * every other mail the plugin sends.
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Daily_Summary {

    /**
     * Class instance
     *
     * @var HRB_Daily_Summary|null
     */
    private static $instance = null;

    /**
     * Cron hook that triggers the send
     */
    const CRON_HOOK = 'hrb_daily_summary';

    /**
     * Option remembering which send time the current schedule was built for
     */
    const SCHEDULE_STAMP = 'hrb_daily_summary_scheduled_for';

    /**
     * Key of the branded email template this summary is rendered from
     */
    const TEMPLATE_KEY = 'daily_summary_admin';

    /**
     * Get class instance
     *
     * @return HRB_Daily_Summary
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - registers the cron hook and admin actions
     */
    private function __construct() {
        add_action(self::CRON_HOOK, [$this, 'run']);
        add_action('admin_init', [$this, 'sync_schedule']);
        add_action('admin_init', [$this, 'handle_send_now']);

        // Saving the settings happens over AJAX, i.e. after admin_init has
        // already run, so react to the option writes as well and the schedule
        // is right the moment the user hits save.
        add_action('update_option_hrb_daily_summary_time', [$this, 'sync_schedule']);
        add_action('update_option_hrb_daily_summary_enabled', [$this, 'sync_schedule']);
        add_action('add_option_hrb_daily_summary_time', [$this, 'sync_schedule']);
        add_action('add_option_hrb_daily_summary_enabled', [$this, 'sync_schedule']);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    // -----------------------------------------------------------------
    // Scheduling
    // -----------------------------------------------------------------

    /**
     * Normalise a submitted time to HH:MM
     *
     * Accepts "9:5", "09:05", "09:05:00" and returns "09:05". Anything that is
     * not a real time of day falls back to midnight, which is the default.
     *
     * @since 1.6.0
     * @param mixed $value Raw value
     * @return string Time as HH:MM
     */
    public static function normalize_time($value) {
        $value = trim((string) $value);

        if (!preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $value, $m)) {
            return '00:00';
        }

        $hours   = (int) $m[1];
        $minutes = (int) $m[2];

        if ($hours > 23 || $minutes > 59) {
            return '00:00';
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Which calendar day should a run at this moment report on?
     *
     * The reported day is the one that ended at the most recent occurrence of
     * the send time. Anchoring on the scheduled time rather than the moment
     * the job actually ran matters: WP-Cron is traffic-driven, so a 00:00 job
     * can easily fire at 00:07 — and it must still report yesterday, not the
     * seven minutes of the new day.
     *
     * @since 1.6.0
     * @param string $send_time Configured send time, HH:MM
     * @param int    $now_ts    Current timestamp in the plugin timezone
     * @return string Date to report on, Y-m-d
     */
    public static function resolve_summary_date($send_time, $now_ts) {
        $send_time = self::normalize_time($send_time);

        $scheduled_today = strtotime(date('Y-m-d', $now_ts) . ' ' . $send_time . ':00');

        // Before today's send time the last run was yesterday's.
        $occurrence = ($now_ts >= $scheduled_today)
            ? $scheduled_today
            : strtotime('-1 day', $scheduled_today);

        // One second earlier is inside the day that just closed.
        return date('Y-m-d', $occurrence - 1);
    }

    /**
     * Timestamp of the next run, in the plugin timezone
     *
     * @since 1.6.0
     * @param string        $send_time Configured send time, HH:MM
     * @param int|null      $now_ts    Reference timestamp (defaults to now)
     * @param DateTimeZone  $timezone  Timezone to interpret the time in
     * @return int UTC timestamp
     */
    public static function next_run_timestamp($send_time, $now_ts = null, $timezone = null) {
        $send_time = self::normalize_time($send_time);
        $timezone  = $timezone ?: self::timezone();

        $now = new DateTime('@' . ($now_ts !== null ? $now_ts : time()));
        $now->setTimezone($timezone);

        $next = new DateTime($now->format('Y-m-d') . ' ' . $send_time . ':00', $timezone);

        if ($next->getTimestamp() <= $now->getTimestamp()) {
            $next->modify('+1 day');
        }

        return $next->getTimestamp();
    }

    /**
     * Keep the cron schedule in step with the settings
     *
     * Self-healing rather than hooked to option updates: whatever changed the
     * setting, the next admin request puts the schedule right.
     *
     * @since 1.6.0
     */
    public function sync_schedule() {
        // Read the raw options rather than HRB_Settings::get(): when this runs
        // from update_option_* the settings cache has not been refreshed yet
        // and would still hold the previous value.
        $enabled   = (bool) get_option('hrb_daily_summary_enabled', 0);
        $send_time = self::normalize_time(get_option('hrb_daily_summary_time', '00:00'));

        $scheduled = wp_next_scheduled(self::CRON_HOOK);

        if (!$enabled) {
            if ($scheduled) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                delete_option(self::SCHEDULE_STAMP);
            }
            return;
        }

        // Already scheduled for exactly this time: nothing to do.
        if ($scheduled && get_option(self::SCHEDULE_STAMP) === $send_time) {
            return;
        }

        if ($scheduled) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }

        wp_schedule_event(self::next_run_timestamp($send_time), 'daily', self::CRON_HOOK);
        update_option(self::SCHEDULE_STAMP, $send_time);
    }

    /**
     * Handle the "Send summary now" link on the settings screen
     *
     * @since 1.6.0
     */
    public function handle_send_now() {
        if (empty($_GET['hrb-send-daily-summary']) || !current_user_can('hrb_manage_settings')) {
            return;
        }

        check_admin_referer('hrb_send_daily_summary');

        $sent = $this->run(true);

        set_transient(
            'hrb_daily_summary_notice',
            $sent > 0
                ? sprintf(
                    /* translators: %d: number of addresses the summary went to */
                    _n('Daily summary sent to %d address.', 'Daily summary sent to %d addresses.', $sent, 'hourly-room-booking'),
                    $sent
                )
                : __('The daily summary could not be sent. Check that at least one recipient is configured.', 'hourly-room-booking'),
            60
        );

        wp_safe_redirect(self_admin_url('admin.php?page=hrb-settings'));
        exit;
    }

    // -----------------------------------------------------------------
    // Sending
    // -----------------------------------------------------------------

    /**
     * Build and send the summary
     *
     * @since 1.6.0
     * @param bool $force Send even when the feature is switched off (manual test)
     * @return int Number of addresses the mail was accepted for
     */
    public function run($force = false) {
        $settings = HRB_Settings::getInstance();

        if (!$force && !$settings->get('hrb_daily_summary_enabled', 0)) {
            return 0;
        }

        $recipients = $this->get_recipients();
        if (empty($recipients)) {
            return 0;
        }

        $date    = self::resolve_summary_date(
            $settings->get('hrb_daily_summary_time', '00:00'),
            self::local_time()
        );
        $figures = $this->collect($date);
        $subject = $this->render_subject($figures);
        $message = $this->render_html($figures);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('hrb_company_name', get_bloginfo('name'))
                . ' <' . get_option('hrb_company_email', get_option('admin_email')) . '>',
        ];

        // One mail per address, so a single bad recipient cannot suppress the
        // summary for everyone else.
        $sent = 0;
        foreach ($recipients as $recipient) {
            if (wp_mail($recipient, $subject, $message, $headers)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Who receives the summary
     *
     * Falls back to the team notification addresses when no dedicated list is
     * configured, so switching the feature on is a single click.
     *
     * @since 1.6.0
     * @return array
     */
    public function get_recipients() {
        $settings = HRB_Settings::getInstance();

        $recipients = $settings->get_list('hrb_daily_summary_emails');

        if (empty($recipients)) {
            $recipients = $settings->get_notification_recipients();
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            $email = sanitize_email($recipient);
            $key   = strtolower($email);

            if (!empty($email) && is_email($email) && !isset($unique[$key])) {
                $unique[$key] = $email;
            }
        }

        /**
         * Filter the addresses that receive the daily summary.
         *
         * @since 1.6.0
         * @param array $recipients
         */
        return apply_filters('hrb_daily_summary_recipients', array_values($unique));
    }

    // -----------------------------------------------------------------
    // Figures
    // -----------------------------------------------------------------

    /**
     * Gather one day's figures
     *
     * @since 1.6.0
     * @param string $date Day to report on, Y-m-d
     * @return array
     */
    public function collect($date) {
        global $wpdb;

        $bookings = $wpdb->prefix . 'hrb_bookings';
        $rooms    = $wpdb->prefix . 'hrb_rooms';
        $payments = $wpdb->prefix . 'hrb_payments';

        $figures = [
            'date'              => $date,
            'total'             => 0,
            'by_status'         => [],
            'by_payment_status' => [],
            'hours'             => 0.0,
            'value'             => 0.0,
            'rooms'             => [],
            'collected'         => 0.0,
            'collected_count'   => 0,
            'outstanding'       => 0.0,
            'cancellation_fees' => 0.0,
        ];

        // Everything below is about the bookings *entered* on this day,
        // whatever date they are for: a booking taken today for the 12th
        // belongs in today's summary, together with its value.
        $status_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS bookings
             FROM {$bookings}
             WHERE DATE(created_at) = %s
             GROUP BY status",
            $date
        ));

        foreach ((array) $status_rows as $row) {
            $figures['by_status'][$row->status] = (int) $row->bookings;
            $figures['total'] += (int) $row->bookings;
        }

        // Payment status of those same bookings.
        $payment_status_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT payment_status, COUNT(*) AS bookings
             FROM {$bookings}
             WHERE DATE(created_at) = %s
             GROUP BY payment_status",
            $date
        ));

        foreach ((array) $payment_status_rows as $row) {
            $key = ('' === (string) $row->payment_status) ? 'pending' : $row->payment_status;

            $figures['by_payment_status'][$key] =
                (isset($figures['by_payment_status'][$key]) ? $figures['by_payment_status'][$key] : 0)
                + (int) $row->bookings;
        }

        // Hours and value of the bookings that actually stand.
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(total_hours), 0) AS hours,
                    COALESCE(SUM(total_amount), 0) AS value
             FROM {$bookings}
             WHERE DATE(created_at) = %s AND status NOT IN ('cancelled', 'no_show')",
            $date
        ));

        if ($totals) {
            $figures['hours'] = (float) $totals->hours;
            $figures['value'] = (float) $totals->value;
        }

        // Which rooms the new bookings were taken for.
        $room_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.name AS room_name,
                    COUNT(b.id) AS bookings,
                    COALESCE(SUM(b.total_hours), 0) AS hours,
                    COALESCE(SUM(b.total_amount), 0) AS value
             FROM {$bookings} b
             INNER JOIN {$rooms} r ON b.room_id = r.id
             WHERE DATE(b.created_at) = %s AND b.status NOT IN ('cancelled', 'no_show')
             GROUP BY b.room_id, r.name
             ORDER BY value DESC, r.name ASC",
            $date
        ));

        foreach ((array) $room_rows as $row) {
            $figures['rooms'][] = [
                'name'     => $row->room_name,
                'bookings' => (int) $row->bookings,
                'hours'    => (float) $row->hours,
                'value'    => (float) $row->value,
            ];
        }

        // Money actually taken on the day, whichever booking it belonged to.
        // This one deliberately stays on the payment date rather than the
        // booking's creation date — it answers "what came in today".
        $collected = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS total
             FROM {$payments}
             WHERE DATE(COALESCE(processed_at, created_at)) = %s
             AND status IN ('completed', 'paid')",
            $date
        ));

        if ($collected) {
            $figures['collected_count'] = (int) $collected->payments;
            $figures['collected']       = (float) $collected->total;
        }

        // Still to be collected on the bookings taken today.
        $figures['outstanding'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM {$payments} p
             INNER JOIN {$bookings} b ON p.booking_id = b.id
             WHERE DATE(b.created_at) = %s AND p.status = 'pending'",
            $date
        ));

        // Cancellation fees on the bookings taken today.
        $figures['cancellation_fees'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(cancellation_fee), 0)
             FROM {$bookings}
             WHERE DATE(created_at) = %s",
            $date
        ));

        /**
         * Filter the figures that go into the daily summary.
         *
         * @since 1.6.0
         * @param array  $figures
         * @param string $date
         */
        return apply_filters('hrb_daily_summary_figures', $figures, $date);
    }

    // -----------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------

    /**
     * Render the summary email
     *
     * @since 1.6.0
     * @param array $figures Output of collect()
     * @return string HTML
     */
    public function render_html(array $figures) {
        $template = $this->get_template();

        return $this->fill_template($template['html_content'], $figures);
    }

    /**
     * Subject line for the summary
     *
     * @since 1.6.0
     * @param array $figures Output of collect()
     * @return string
     */
    public function render_subject(array $figures) {
        $template = $this->get_template();

        return wp_strip_all_tags($this->fill_template($template['subject'], $figures));
    }

    /**
     * Load the summary email template
     *
     * Prefers the row in the email-templates table so the team can edit the
     * wording and layout on the Email Templates screen like every other mail.
     * Falls back to the bundled copy when the row is missing.
     *
     * @since 1.6.0
     * @return array {
     *     @type string $subject
     *     @type string $html_content
     * }
     */
    private function get_template() {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT subject, html_content
             FROM {$wpdb->prefix}hrb_email_templates
             WHERE template_key = %s AND template_type = 'admin' AND is_active = 1",
            self::TEMPLATE_KEY
        ));

        if ($row && !empty($row->html_content)) {
            return [
                'subject'      => (string) $row->subject,
                'html_content' => (string) $row->html_content,
            ];
        }

        return self::bundled_template();
    }

    /**
     * The bundled copy of the summary template
     *
     * @since 1.6.0
     * @return array
     */
    public static function bundled_template() {
        $fallback = [
            'subject'      => 'Tageszusammenfassung {summary_date} - {company_name}',
            'html_content' => '<p>{summary_date}</p><p>{total_bookings}</p><p>{total_revenue}</p>',
        ];

        $file = HRB_PLUGIN_DIR . 'includes/email-templates-data.php';
        if (!file_exists($file)) {
            return $fallback;
        }

        $templates = include $file;
        if (!is_array($templates)) {
            return $fallback;
        }

        foreach ($templates as $template) {
            if (isset($template['template_key']) && $template['template_key'] === self::TEMPLATE_KEY) {
                return [
                    'subject'      => (string) $template['subject'],
                    'html_content' => (string) $template['html_content'],
                ];
            }
        }

        return $fallback;
    }

    /**
     * Replace the summary placeholders in a template string
     *
     * @since 1.6.0
     * @param string $content Template with {placeholders}
     * @param array  $figures Output of collect()
     * @return string
     */
    private function fill_template($content, array $figures) {
        $company_name = get_option('hrb_company_name', get_bloginfo('name'));
        $company_logo = get_option('hrb_company_logo', '');

        $logo_html = '';
        if ($company_logo) {
            $logo_html = '<img src="' . esc_url($company_logo) . '" alt="' . esc_attr($company_name) . '">';
        }

        $status = $figures['by_status'];

        $replacements = [
            '{summary_date}'        => date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($figures['date'])),
            '{total_bookings}'      => (string) $figures['total'],
            '{confirmed_bookings}'  => (string) (isset($status['confirmed']) ? $status['confirmed'] : 0),
            '{pending_bookings}'    => (string) (isset($status['pending']) ? $status['pending'] : 0),
            '{cancelled_bookings}'  => (string) (isset($status['cancelled']) ? $status['cancelled'] : 0),
            '{completed_bookings}'  => (string) (isset($status['completed']) ? $status['completed'] : 0),
            '{no_show_bookings}'    => (string) (isset($status['no_show']) ? $status['no_show'] : 0),
            '{hours_booked}'        => $this->format_hours($figures['hours']),
            '{total_revenue}'       => hrb_format_amount($figures['value']),
            '{payments_received}'   => hrb_format_amount($figures['collected'])
                . ' (' . (int) $figures['collected_count'] . ')',
            '{outstanding}'         => hrb_format_amount($figures['outstanding']),
            '{cancellation_fees}'   => hrb_format_amount($figures['cancellation_fees']),
            '{payment_status_rows}' => $this->render_payment_status_rows($figures),
            '{rooms_rows}'          => $this->render_room_rows($figures),
            '{company_logo_html}'   => $logo_html,
            '{company_logo}'        => esc_url($company_logo),
            '{company_name}'        => esc_html($company_name),
            '{company_phone}'       => esc_html(get_option('hrb_company_phone', '')),
            '{company_email}'       => esc_html(get_option('hrb_company_email', get_option('admin_email'))),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Table rows for the payment-status breakdown
     *
     * @param array $figures
     * @return string
     */
    private function render_payment_status_rows(array $figures) {
        $labels = [
            'paid'      => __('Paid', 'hourly-room-booking'),
            'completed' => __('Paid', 'hourly-room-booking'),
            'pending'   => __('Pending', 'hourly-room-booking'),
            'cancelled' => __('Cancelled', 'hourly-room-booking'),
            'refunded'  => __('Refunded', 'hourly-room-booking'),
            'failed'    => __('Failed', 'hourly-room-booking'),
        ];

        if (empty($figures['by_payment_status'])) {
            return '<tr><td colspan="2" class="empty">'
                . esc_html__('No bookings were created on this day.', 'hourly-room-booking')
                . '</td></tr>';
        }

        $html = '';
        foreach ($figures['by_payment_status'] as $status => $count) {
            $label = isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));

            $html .= '<tr><td>' . esc_html($label) . '</td>'
                . '<td class="num">' . (int) $count . '</td></tr>';
        }

        return $html;
    }

    /**
     * Table rows for the per-room breakdown
     *
     * @param array $figures
     * @return string
     */
    private function render_room_rows(array $figures) {
        if (empty($figures['rooms'])) {
            return '<tr><td colspan="4" class="empty">'
                . esc_html__('No bookings were created on this day.', 'hourly-room-booking')
                . '</td></tr>';
        }

        $html = '';
        foreach ($figures['rooms'] as $room) {
            $html .= '<tr>'
                . '<td>' . esc_html($room['name']) . '</td>'
                . '<td class="num">' . (int) $room['bookings'] . '</td>'
                . '<td class="num">' . esc_html($this->format_hours($room['hours'])) . '</td>'
                . '<td class="num">' . esc_html(hrb_format_amount($room['value'])) . '</td>'
                . '</tr>';
        }

        return $html;
    }

    /**
     * Format an hour count without trailing noise ("3" not "3.00")
     *
     * @param float $hours
     * @return string
     */
    private function format_hours($hours) {
        $hours = (float) $hours;

        // Whole hours read better without decimals ("3" rather than "3,00").
        return number_format_i18n($hours, floor($hours) == $hours ? 0 : 2);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * The plugin's configured timezone
     *
     * @return DateTimeZone
     */
    private static function timezone() {
        $name = get_option('hrb_timezone', '');

        if (!empty($name)) {
            try {
                return new DateTimeZone($name);
            } catch (Exception $e) {
                // Fall through to the WordPress timezone.
            }
        }

        return wp_timezone();
    }

    /**
     * Current timestamp shifted into the plugin timezone
     *
     * resolve_summary_date() works with date()/strtotime(), which run in
     * whatever timezone PHP is set to, so the offset is folded in here.
     *
     * @return int
     */
    private static function local_time() {
        $now = new DateTime('now', self::timezone());

        return $now->getTimestamp() + $now->getOffset();
    }
}
