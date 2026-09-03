<?php
/**
 * Daily Summary Class
 *
 * Sends a scheduled summary of one day's figures — bookings, per-room usage
 * and revenue — to a configurable list of addresses.
 *
 * The summary always covers one whole calendar day: the day that ended at the
 * configured send time. At the default 00:00 that is the day that just
 * finished; set it to 18:00 and the mail covers the current day up to that
 * point. See resolve_summary_date().
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
        $subject = sprintf(
            /* translators: 1: company name, 2: date the summary covers */
            __('%1$s — daily summary for %2$s', 'hourly-room-booking'),
            get_option('hrb_company_name', get_bloginfo('name')),
            date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($date))
        );

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
            'hours'             => 0.0,
            'value'             => 0.0,
            'rooms'             => [],
            'collected'         => 0.0,
            'collected_count'   => 0,
            'outstanding'       => 0.0,
            'created'           => 0,
            'created_value'     => 0.0,
            'cancellation_fees' => 0.0,
        ];

        // Bookings taking place on the day, broken down by status.
        $status_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS bookings
             FROM {$bookings}
             WHERE booking_date = %s
             GROUP BY status",
            $date
        ));

        foreach ((array) $status_rows as $row) {
            $figures['by_status'][$row->status] = (int) $row->bookings;
            $figures['total'] += (int) $row->bookings;
        }

        // Hours and value of the bookings that actually stand.
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(total_hours), 0) AS hours,
                    COALESCE(SUM(total_amount), 0) AS value
             FROM {$bookings}
             WHERE booking_date = %s AND status NOT IN ('cancelled', 'no_show')",
            $date
        ));

        if ($totals) {
            $figures['hours'] = (float) $totals->hours;
            $figures['value'] = (float) $totals->value;
        }

        // Per-room usage.
        $room_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.name AS room_name,
                    COUNT(b.id) AS bookings,
                    COALESCE(SUM(b.total_hours), 0) AS hours,
                    COALESCE(SUM(b.total_amount), 0) AS value
             FROM {$bookings} b
             INNER JOIN {$rooms} r ON b.room_id = r.id
             WHERE b.booking_date = %s AND b.status NOT IN ('cancelled', 'no_show')
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
        $collected = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS total
             FROM {$payments}
             WHERE DATE(processed_at) = %s AND status IN ('completed', 'paid')",
            $date
        ));

        if ($collected) {
            $figures['collected_count'] = (int) $collected->payments;
            $figures['collected']       = (float) $collected->total;
        }

        // Still to be collected for the day's bookings.
        $figures['outstanding'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM {$payments} p
             INNER JOIN {$bookings} b ON p.booking_id = b.id
             WHERE b.booking_date = %s AND p.status = 'pending'",
            $date
        ));

        // Bookings entered on the day, whichever day they are for.
        $created = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS bookings, COALESCE(SUM(total_amount), 0) AS value
             FROM {$bookings}
             WHERE DATE(created_at) = %s",
            $date
        ));

        if ($created) {
            $figures['created']       = (int) $created->bookings;
            $figures['created_value'] = (float) $created->value;
        }

        // Cancellation fees charged for the day's bookings.
        $figures['cancellation_fees'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(cancellation_fee), 0)
             FROM {$bookings}
             WHERE booking_date = %s",
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
        $date_label = date_i18n(
            get_option('hrb_date_format', 'd.m.Y'),
            strtotime($figures['date'])
        );

        $status_labels = [
            'confirmed' => __('Confirmed', 'hourly-room-booking'),
            'pending'   => __('Pending', 'hourly-room-booking'),
            'completed' => __('Completed', 'hourly-room-booking'),
            'cancelled' => __('Cancelled', 'hourly-room-booking'),
            'no_show'   => __('No show', 'hourly-room-booking'),
        ];

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>' . esc_html($date_label) . '</title></head>';
        $html .= '<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#333;">';
        $html .= '<div style="max-width:640px;margin:0 auto;padding:24px;">';

        // Header
        $html .= '<div style="background:#0073aa;color:#fff;padding:20px 24px;border-radius:6px 6px 0 0;">';
        $html .= '<div style="font-size:18px;font-weight:bold;">'
            . esc_html(get_option('hrb_company_name', get_bloginfo('name'))) . '</div>';
        $html .= '<div style="font-size:14px;opacity:0.9;margin-top:4px;">'
            . sprintf(
                /* translators: %s: the date the summary covers */
                esc_html__('Daily summary for %s', 'hourly-room-booking'),
                esc_html($date_label)
            ) . '</div>';
        $html .= '</div>';

        $html .= '<div style="background:#fff;padding:24px;border:1px solid #e1e5e9;border-top:0;border-radius:0 0 6px 6px;">';

        // Headline figures
        $html .= $this->render_stat_row([
            __('Bookings', 'hourly-room-booking')       => (string) $figures['total'],
            __('Hours booked', 'hourly-room-booking')   => $this->format_hours($figures['hours']),
            __('Booking value', 'hourly-room-booking')  => hrb_format_amount($figures['value']),
        ]);

        $html .= $this->render_stat_row([
            __('Payments received', 'hourly-room-booking') => hrb_format_amount($figures['collected'])
                . ' (' . (int) $figures['collected_count'] . ')',
            __('Outstanding', 'hourly-room-booking')       => hrb_format_amount($figures['outstanding']),
            __('New bookings', 'hourly-room-booking')      => (string) $figures['created']
                . ' · ' . hrb_format_amount($figures['created_value']),
        ]);

        // Status breakdown
        if (!empty($figures['by_status'])) {
            $rows = [];
            foreach ($figures['by_status'] as $status => $count) {
                $label = $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                $rows[] = [$label, (string) $count];
            }
            $html .= $this->render_table(__('By status', 'hourly-room-booking'), ['', ''], $rows);
        }

        // Per room
        if (!empty($figures['rooms'])) {
            $rows = [];
            foreach ($figures['rooms'] as $room) {
                $rows[] = [
                    $room['name'],
                    (string) $room['bookings'],
                    $this->format_hours($room['hours']),
                    hrb_format_amount($room['value']),
                ];
            }
            $html .= $this->render_table(
                __('By room', 'hourly-room-booking'),
                [
                    __('Room', 'hourly-room-booking'),
                    __('Bookings', 'hourly-room-booking'),
                    __('Hours', 'hourly-room-booking'),
                    __('Value', 'hourly-room-booking'),
                ],
                $rows
            );
        } else {
            $html .= '<p style="color:#646970;margin:20px 0 0;">'
                . esc_html__('No bookings for this day.', 'hourly-room-booking') . '</p>';
        }

        if ($figures['cancellation_fees'] > 0) {
            $html .= '<p style="color:#646970;margin:16px 0 0;font-size:13px;">'
                . sprintf(
                    /* translators: %s: formatted amount */
                    esc_html__('Cancellation fees for this day: %s', 'hourly-room-booking'),
                    esc_html(hrb_format_amount($figures['cancellation_fees']))
                ) . '</p>';
        }

        $html .= '</div>';
        $html .= '<div style="text-align:center;color:#8c8f94;font-size:12px;padding:16px;">'
            . esc_html__('Automated summary from the Hourly Room Booking plugin.', 'hourly-room-booking')
            . '</div>';
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Render a row of headline numbers
     *
     * @param array $stats Label => value
     * @return string
     */
    private function render_stat_row(array $stats) {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;"><tr>';

        foreach ($stats as $label => $value) {
            $html .= '<td style="width:33%;padding:12px;background:#f6f7f7;border-radius:4px;vertical-align:top;">';
            $html .= '<div style="font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:0.03em;">'
                . esc_html($label) . '</div>';
            $html .= '<div style="font-size:18px;font-weight:bold;color:#1d2327;margin-top:4px;">'
                . esc_html($value) . '</div>';
            $html .= '</td><td style="width:8px;"></td>';
        }

        $html .= '</tr></table>';

        return $html;
    }

    /**
     * Render a titled data table
     *
     * @param string $title   Section heading
     * @param array  $headers Column headings ('' to omit the header row)
     * @param array  $rows    List of rows, each a list of cell strings
     * @return string
     */
    private function render_table($title, array $headers, array $rows) {
        $html  = '<h3 style="font-size:14px;color:#1d2327;margin:24px 0 8px;">' . esc_html($title) . '</h3>';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'style="border-collapse:collapse;font-size:14px;">';

        if (implode('', $headers) !== '') {
            $html .= '<tr>';
            foreach ($headers as $index => $header) {
                $align = $index === 0 ? 'left' : 'right';
                $html .= '<th style="text-align:' . $align . ';padding:6px 8px;border-bottom:2px solid #e1e5e9;'
                    . 'font-size:12px;color:#646970;text-transform:uppercase;">' . esc_html($header) . '</th>';
            }
            $html .= '</tr>';
        }

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (array_values($row) as $index => $cell) {
                $align = $index === 0 ? 'left' : 'right';
                $html .= '<td style="text-align:' . $align . ';padding:8px;border-bottom:1px solid #f0f0f1;">'
                    . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

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
