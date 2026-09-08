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
     * The times the summary can be scheduled for
     *
     * Half-hourly, in 24-hour form. The settings screen renders these as a
     * <select> rather than an <input type="time">, because that input is drawn
     * in the browser's own locale — an en-US browser shows 12-hour AM/PM no
     * matter what the site is set to, and nothing on the page can change it.
     *
     * A stored time that is not on the half-hour grid is kept and sorted into
     * place, so opening the settings screen never quietly moves a send time
     * somebody set deliberately.
     *
     * @since 1.10.1
     * @param string $current Time currently stored, HH:MM
     * @return array List of HH:MM strings
     */
    public static function send_time_choices($current = '') {
        $choices = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
            $choices[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        $current = self::normalize_time($current);

        if ('' !== $current && !in_array($current, $choices, true)) {
            $choices[] = $current;
            sort($choices);
        }

        return $choices;
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
    /**
     * Which channel a payment method belongs to
     *
     * Money handed over at the venue - "onsite" and "cash" - is one thing to
     * the team; PayPal is another. The plugin already treats those two as a
     * single group wherever it decides whether a booking is settled on
     * arrival, so the summary splits them the same way rather than inventing
     * a second rule. Anything else (a bank transfer, say) is reported on its
     * own line and counted as "other".
     *
     * @since 1.9.0
     * @param string $method Value of the payment_method column
     * @return string onsite|paypal|other
     */
    public static function payment_channel($method) {
        $method = strtolower(trim((string) $method));

        if (in_array($method, ['onsite', 'cash'], true)) {
            return 'onsite';
        }

        if ('paypal' === $method) {
            return 'paypal';
        }

        return 'other';
    }
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
            'outstanding_bookings'      => 0.0,
            'pending_cancellation_fees' => 0.0,
            'cancellation_fees' => 0.0,
            'bookings'          => [],
            'by_payment_method' => [],
            'channels'          => [
                'onsite' => ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0],
                'paypal' => ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0],
                'other'  => ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0],
            ],
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

        // How the day splits between money taken at the venue and money taken
        // through PayPal. Bookings are counted whatever their status, so the
        // figures add up to {total_bookings}; the value only counts the ones
        // that still stand, so it adds up to {total_revenue}.
        $method_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(payment_method, ''), 'unknown') AS method,
                    COUNT(*) AS bookings,
                    COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'no_show')
                                      THEN total_amount ELSE 0 END), 0) AS value
             FROM {$bookings}
             WHERE DATE(created_at) = %s
             GROUP BY method",
            $date
        ));

        foreach ((array) $method_rows as $row) {
            $method = strtolower((string) $row->method);

            $figures['by_payment_method'][$method] = [
                'bookings'  => (int) $row->bookings,
                'value'     => (float) $row->value,
                'collected' => 0.0,
            ];
        }

        // And the money that actually arrived on the day, by method. A booking
        // taken last week but paid for today belongs here, not above.
        $method_collected = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(payment_method, ''), 'unknown') AS method,
                    COALESCE(SUM(amount), 0) AS total
             FROM {$payments}
             WHERE DATE(COALESCE(processed_at, created_at)) = %s
             AND status IN ('completed', 'paid')
             GROUP BY method",
            $date
        ));

        foreach ((array) $method_collected as $row) {
            $method = strtolower((string) $row->method);

            if (!isset($figures['by_payment_method'][$method])) {
                $figures['by_payment_method'][$method] = [
                    'bookings'  => 0,
                    'value'     => 0.0,
                    'collected' => 0.0,
                ];
            }

            $figures['by_payment_method'][$method]['collected'] = (float) $row->total;
        }

        // Roll the individual methods up into the two the team cares about.
        foreach ($figures['by_payment_method'] as $method => $totals) {
            $channel = self::payment_channel($method);

            $figures['channels'][$channel]['bookings']  += $totals['bookings'];
            $figures['channels'][$channel]['value']     += $totals['value'];
            $figures['channels'][$channel]['collected'] += $totals['collected'];
        }

        // Biggest earner first, so the table opens with what matters.
        uasort($figures['by_payment_method'], function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        // The bookings themselves, so the summary can name them rather than
        // only counting them. Cancelled and no-show bookings are left out: the
        // list answers "who is coming and who still owes money", and neither
        // does. A booking's own first_name/last_name wins over the customer
        // record, because that is what an admin typed on the booking.
        $booking_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.booking_reference, b.start_time, b.end_time, b.total_amount,
                    b.payment_method, b.payment_status, b.is_anonymous,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(b.first_name,''), ' ', COALESCE(b.last_name,''))), ''),
                             NULLIF(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), ''),
                             '') AS customer_name,
                    r.name AS room_name
             FROM {$bookings} b
             LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             LEFT JOIN {$rooms} r ON b.room_id = r.id
             WHERE DATE(b.created_at) = %s AND b.status NOT IN ('cancelled', 'no_show')
             ORDER BY b.start_time ASC, b.id ASC",
            $date
        ));

        $settled = ['paid', 'completed'];

        foreach ((array) $booking_rows as $row) {
            $method = strtolower(trim((string) $row->payment_method));
            $paid   = in_array(strtolower(trim((string) $row->payment_status)), $settled, true);

            $figures['bookings'][] = [
                'reference' => (string) $row->booking_reference,
                'customer'  => (int) $row->is_anonymous === 1
                    ? __('Anonymous', 'hourly-room-booking')
                    : ((string) $row->customer_name !== '' ? (string) $row->customer_name : __('Guest', 'hourly-room-booking')),
                'room'      => (string) $row->room_name,
                'start'     => substr((string) $row->start_time, 0, 5),
                'end'       => substr((string) $row->end_time, 0, 5),
                'amount'    => (float) $row->total_amount,
                'method'    => $method,
                'channel'   => self::payment_channel($method),
                'paid'      => $paid,
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

        // Still to be collected on the bookings taken today. This total keeps
        // its old meaning - it includes any cancellation fee - because a site
        // may have built a template on {outstanding}; the two halves below are
        // what the summary itself now shows.
        $figures['outstanding'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM {$payments} p
             INNER JOIN {$bookings} b ON p.booking_id = b.id
             WHERE DATE(b.created_at) = %s AND p.status = 'pending'",
            $date
        ));

        // The booking money still owed, with cancellation fees taken out. The
        // two are chased differently - one is a room somebody still has to pay
        // for, the other is a penalty on a booking that is gone - so the
        // summary keeps them apart.
        $figures['outstanding_bookings'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM {$payments} p
             INNER JOIN {$bookings} b ON p.booking_id = b.id
             WHERE DATE(b.created_at) = %s AND p.status = 'pending'
             AND (p.transaction_id NOT LIKE %s OR p.transaction_id IS NULL)",
            $date,
            $wpdb->esc_like('CANCELFEE_') . '%'
        ));

        // Cancellation fees charged but not yet paid. Scoped to the day the fee
        // was raised rather than the day the booking was taken: a fee charged
        // today on a booking from last month is today's outstanding money.
        $figures['pending_cancellation_fees'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM {$payments}
             WHERE DATE(created_at) = %s AND status = 'pending'
             AND transaction_id LIKE %s",
            $date,
            $wpdb->esc_like('CANCELFEE_') . '%'
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

        // collect() always fills these in, but the figures pass through the
        // hrb_daily_summary_figures filter on the way here and a site could
        // hand back a trimmed array. Missing channels read as zero rather
        // than blowing up the one mail nobody is watching being sent.
        $blank    = ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0];
        $channels = isset($figures['channels']) ? (array) $figures['channels'] : [];

        foreach (['onsite', 'paypal', 'other'] as $channel) {
            $channels[$channel] = isset($channels[$channel])
                ? ((array) $channels[$channel] + $blank)
                : $blank;
        }

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
            '{payments_received}'   => hrb_format_amount($figures['collected']),
            '{payments_count}'      => (string) (int) $figures['collected_count'],
            '{outstanding}'         => hrb_format_amount($figures['outstanding']),
            '{outstanding_bookings}'      => hrb_format_amount(
                isset($figures['outstanding_bookings']) ? $figures['outstanding_bookings'] : 0
            ),
            '{pending_cancellation_fees}' => hrb_format_amount(
                isset($figures['pending_cancellation_fees']) ? $figures['pending_cancellation_fees'] : 0
            ),
            '{cancellation_fees}'   => hrb_format_amount($figures['cancellation_fees']),
            '{onsite_bookings}'     => (string) $channels['onsite']['bookings'],
            '{onsite_revenue}'      => hrb_format_amount($channels['onsite']['value']),
            '{onsite_received}'     => hrb_format_amount($channels['onsite']['collected']),
            '{paypal_bookings}'     => (string) $channels['paypal']['bookings'],
            '{paypal_revenue}'      => hrb_format_amount($channels['paypal']['value']),
            '{paypal_received}'     => hrb_format_amount($channels['paypal']['collected']),
            '{other_bookings}'      => (string) $channels['other']['bookings'],
            '{other_revenue}'       => hrb_format_amount($channels['other']['value']),
            '{other_received}'      => hrb_format_amount($channels['other']['collected']),
            '{payment_method_rows}' => $this->render_payment_method_rows($figures),
            '{day_narrative}'       => $this->render_narrative($figures),
            '{unpaid_booking_rows}' => $this->render_unpaid_rows($figures),
            '{split_bar}'           => $this->render_split_bar($figures),
            '{split_legend}'        => $this->render_split_legend($figures),
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
     * Colour for a payment method
     *
     * Fixed per method, never per position in the table: a quiet day that drops
     * one method must not repaint the others. The hues are the first four slots
     * of the validated categorical palette, in order, and anything beyond them
     * is deliberately neutral rather than a new colour - a fifth generated hue
     * is where a palette stops being readable.
     *
     * @since 1.10.2
     * @param string $method Value of the payment_method column
     * @return string Hex colour
     */
    public static function method_color($method) {
        $colors = [
            'onsite'        => '#2a78d6',
            'paypal'        => '#eb6834',
            'cash'          => '#1baf7a',
            'bank_transfer' => '#eda100',
        ];

        $method = strtolower(trim((string) $method));

        return isset($colors[$method]) ? $colors[$method] : '#9aa3ad';
    }

    /**
     * Colour for a booking or payment status
     *
     * The reserved status palette, kept away from the method colours so a state
     * never looks like a series. Every use pairs it with the written status, so
     * the colour is never carrying the meaning on its own.
     *
     * @since 1.10.2
     * @param string $status
     * @return string Hex colour
     */
    public static function status_color($status) {
        $colors = [
            'confirmed' => '#0ca30c',
            'completed' => '#0ca30c',
            'paid'      => '#0ca30c',
            'pending'   => '#fab219',
            'no_show'   => '#ec835a',
            'cancelled' => '#d03b3b',
            'failed'    => '#d03b3b',
            'refunded'  => '#9aa3ad',
        ];

        $status = strtolower(trim((string) $status));

        return isset($colors[$status]) ? $colors[$status] : '#9aa3ad';
    }

    /**
     * A share of a total, as a percentage
     *
     * @since 1.10.2
     * @param float $part
     * @param float $whole
     * @return float 0-100
     */
    public static function share_of($part, $whole) {
        $whole = (float) $whole;

        if ($whole <= 0) {
            return 0.0;
        }

        return (float) max(0, min(100, ((float) $part / $whole) * 100));
    }

    /**
     * A single horizontal bar
     *
     * Built from table cells with bgcolor attributes rather than a styled div:
     * Outlook ignores CSS backgrounds on block elements but honours a table
     * cell, and there is no image to be blocked. A zero-width cell is dropped
     * rather than emitted, because clients disagree about what width="0" means.
     *
     * @since 1.10.2
     * @param float  $percent 0-100
     * @param string $color   Hex fill
     * @return string HTML
     */
    private function bar($percent, $color) {
        $percent = (float) $percent;
        $filled  = (int) round($percent);
        $track   = '#eceef1';

        $cell = function ($width, $bg, $round) {
            return '<td' . ($width !== null ? ' width="' . $width . '%"' : '')
                . ' bgcolor="' . $bg . '" style="background:' . $bg . ';height:10px;'
                . 'line-height:10px;font-size:0;' . $round . '">&nbsp;</td>';
        };

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
              . ' width="100%" style="border-collapse:collapse;table-layout:fixed;"><tr>';

        if ($filled > 0) {
            $round = $filled >= 100 ? 'border-radius:5px;' : 'border-radius:5px 0 0 5px;';
            $html .= $cell($filled, $color, $round);
        }

        if ($filled < 100) {
            $round = $filled > 0 ? 'border-radius:0 5px 5px 0;' : 'border-radius:5px;';
            $html .= $cell(null, $track, $round);
        }

        return $html . '</tr></table>';
    }

    /**
     * The stacked bar showing how the day split between on-site and PayPal
     *
     * A 2px white gap separates the segments so two fills never touch, which is
     * what makes a stacked bar readable when the colours are close in weight.
     *
     * @since 1.10.2
     * @param array $figures
     * @return string HTML
     */
    private function render_split_bar(array $figures) {
        $channels = isset($figures['channels']) ? $figures['channels'] : [];

        $onsite = isset($channels['onsite']['value']) ? (float) $channels['onsite']['value'] : 0.0;
        $paypal = isset($channels['paypal']['value']) ? (float) $channels['paypal']['value'] : 0.0;
        $other  = isset($channels['other']['value']) ? (float) $channels['other']['value'] : 0.0;

        $total = $onsite + $paypal + $other;

        if ($total <= 0) {
            return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
                 . ' style="border-collapse:collapse;"><tr>'
                 . '<td bgcolor="#eceef1" style="background:#eceef1;height:12px;line-height:12px;'
                 . 'font-size:0;border-radius:6px;">&nbsp;</td></tr></table>';
        }

        $segments = [
            ['value' => $onsite, 'color' => self::method_color('onsite')],
            ['value' => $paypal, 'color' => self::method_color('paypal')],
            ['value' => $other,  'color' => '#9aa3ad'],
        ];

        $cells = [];
        foreach ($segments as $segment) {
            $percent = (int) round(self::share_of($segment['value'], $total));
            if ($percent <= 0) {
                continue;
            }
            $cells[] = '<td width="' . $percent . '%" bgcolor="' . $segment['color']
                . '" style="background:' . $segment['color'] . ';height:12px;line-height:12px;'
                . 'font-size:0;">&nbsp;</td>';
        }

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
              . ' style="border-collapse:collapse;table-layout:fixed;border-radius:6px;overflow:hidden;"><tr>';

        $html .= implode(
            '<td width="1%" bgcolor="#ffffff" style="background:#ffffff;height:12px;line-height:12px;font-size:0;">&nbsp;</td>',
            $cells
        );

        return $html . '</tr></table>';
    }

    /**
     * The legend under the split bar
     *
     * Name, amount and share on one line per channel. The written name is what
     * identifies the channel; the colour chip only repeats it.
     *
     * @since 1.10.2
     * @param array $figures
     * @return string HTML
     */
    private function render_split_legend(array $figures) {
        $channels = isset($figures['channels']) ? $figures['channels'] : [];

        $rows = [
            ['key' => 'onsite', 'label' => __('On site', 'hourly-room-booking'), 'color' => self::method_color('onsite')],
            ['key' => 'paypal', 'label' => __('PayPal', 'hourly-room-booking'),  'color' => self::method_color('paypal')],
            ['key' => 'other',  'label' => __('Other', 'hourly-room-booking'),   'color' => '#9aa3ad'],
        ];

        $total = 0.0;
        foreach ($rows as $row) {
            $total += isset($channels[$row['key']]['value']) ? (float) $channels[$row['key']]['value'] : 0.0;
        }

        $html = '';
        foreach ($rows as $row) {
            $value = isset($channels[$row['key']]['value']) ? (float) $channels[$row['key']]['value'] : 0.0;

            // "Other" only earns a line when there is something in it.
            if ('other' === $row['key'] && $value <= 0) {
                continue;
            }

            $share = (int) round(self::share_of($value, $total));

            $html .= '<tr>'
                . '<td style="padding:7px 0;font-size:14px;color:#1f2328;white-space:nowrap;">'
                . '<span style="display:inline-block;width:10px;height:10px;border-radius:2px;'
                . 'background:' . $row['color'] . ';margin-right:8px;"></span>'
                . esc_html($row['label'])
                . '</td>'
                . '<td align="right" style="padding:7px 0;font-size:14px;color:#1f2328;font-weight:600;white-space:nowrap;">'
                . esc_html(hrb_format_amount($value))
                . '</td>'
                . '<td align="right" width="56" style="padding:7px 0;font-size:13px;color:#6b7280;white-space:nowrap;">'
                . $share . '&nbsp;%'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }
    /**
     * The day in sentences
     *
     * Counts on their own do not tell whoever opens the door in the morning
     * what to expect. This says how many bookings came in, how many are
     * already settled, and how many still owe money when they arrive.
     *
     * @since 1.10.4
     * @param array $figures
     * @return string HTML
     */
    private function render_narrative(array $figures) {
        $bookings = isset($figures['bookings']) ? (array) $figures['bookings'] : [];
        $date     = date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($figures['date']));

        $muted = '#6b7280';
        $ink   = '#1f2328';

        if (empty($bookings)) {
            return '<p style="margin:0;font-size:15px;line-height:1.7;color:' . $muted . ';">'
                 . sprintf(
                     /* translators: %s: the date the summary covers */
                     esc_html__('No bookings were taken on %s.', 'hourly-room-booking'),
                     '<strong style="color:' . $ink . ';">' . esc_html($date) . '</strong>'
                 )
                 . '</p>';
        }

        $paid    = [];
        $to_pay  = [];
        $owed    = 0.0;

        foreach ($bookings as $booking) {
            if (!empty($booking['paid'])) {
                $paid[] = $booking;
                continue;
            }

            $to_pay[] = $booking;
            $owed    += (float) $booking['amount'];
        }

        $line = function ($text) use ($muted) {
            return '<p style="margin:0 0 10px 0;font-size:15px;line-height:1.7;color:' . $muted . ';">'
                 . $text . '</p>';
        };

        $strong = function ($text) use ($ink) {
            return '<strong style="color:' . $ink . ';">' . esc_html($text) . '</strong>';
        };

        $html = $line(sprintf(
            /* translators: 1: date, 2: number of bookings */
            esc_html__('On %1$s, %2$s bookings were taken.', 'hourly-room-booking'),
            $strong($date),
            $strong((string) count($bookings))
        ));

        if ($paid) {
            $html .= $line(sprintf(
                /* translators: %s: number of customers who have already paid */
                esc_html__('%s of them have already paid.', 'hourly-room-booking'),
                $strong((string) count($paid))
            ));
        }

        if ($to_pay) {
            $html .= $line(sprintf(
                /* translators: 1: number of customers, 2: total amount still owed */
                esc_html__('%1$s pay on site, %2$s in total:', 'hourly-room-booking'),
                $strong((string) count($to_pay)),
                $strong(hrb_format_amount($owed))
            ));
        }

        return $html;
    }

    /**
     * One line per booking that is still to be paid for
     *
     * These are the ones the desk has to collect money from, so the amount is
     * the point of the row and the rest is there to recognise the booking by.
     *
     * @since 1.10.4
     * @param array $figures
     * @return string HTML table rows
     */
    private function render_unpaid_rows(array $figures) {
        $bookings = isset($figures['bookings']) ? (array) $figures['bookings'] : [];

        $rows = [];
        foreach ($bookings as $booking) {
            if (empty($booking['paid'])) {
                $rows[] = $booking;
            }
        }

        if (empty($rows)) {
            return '<tr><td style="padding:14px 0;color:#6b7280;font-style:italic;font-size:14px;">'
                 . esc_html__('Everything taken today is already paid for.', 'hourly-room-booking')
                 . '</td></tr>';
        }

        $html = '';
        foreach ($rows as $booking) {
            $where = trim(implode(' · ', array_filter([
                $booking['room'],
                ('' !== $booking['start'] ? $booking['start'] . '–' . $booking['end'] : ''),
                $booking['reference'],
            ])));

            $html .= '<tr>'
                . '<td style="padding:11px 12px 11px 0;border-bottom:1px solid #eceef1;font-size:14px;color:#1f2328;">'
                . esc_html($booking['customer'])
                . '<div style="font-size:12px;color:#6b7280;padding-top:2px;">' . esc_html($where) . '</div>'
                . '</td>'
                . '<td align="right" style="padding:11px 0;border-bottom:1px solid #eceef1;font-size:15px;'
                . 'font-weight:700;color:#1f2328;white-space:nowrap;">'
                . esc_html(hrb_format_amount($booking['amount']))
                . '</td>'
                . '</tr>';
        }

        return $html;
    }
    /**
     * Table rows for the on-site / PayPal breakdown
     *
     * One row per method actually used on the day, so a site that only ever
     * takes cash never sees an empty PayPal line.
     *
     * @since 1.9.0
     * @param array $figures
     * @return string
     */
    private function render_payment_method_rows(array $figures) {
        if (empty($figures['by_payment_method'])) {
            return '<tr><td colspan="3" style="padding:14px 0;color:#6b7280;font-style:italic;font-size:14px;">'
                . esc_html__('No bookings were created on this day.', 'hourly-room-booking')
                . '</td></tr>';
        }

        // Bars are drawn against the largest method, not the total, so the
        // biggest one fills the row and the rest are read against it. Against
        // the total, a day split four ways would be four short stubs.
        $peak = 0.0;
        foreach ($figures['by_payment_method'] as $totals) {
            $peak = max($peak, (float) $totals['value']);
        }

        $html = '';
        foreach ($figures['by_payment_method'] as $method => $totals) {
            $label = ('unknown' === $method)
                ? __('Not specified', 'hourly-room-booking')
                : hrb_get_payment_method_label($method);

            $color = self::method_color($method);

            $html .= '<tr>'
                . '<td style="padding:12px 12px 12px 0;border-bottom:1px solid #eceef1;font-size:14px;'
                . 'color:#1f2328;white-space:nowrap;">'
                . '<span style="display:inline-block;width:10px;height:10px;border-radius:2px;'
                . 'background:' . $color . ';margin-right:8px;"></span>'
                . esc_html($label)
                . '</td>'
                . '<td style="padding:12px 12px;border-bottom:1px solid #eceef1;width:34%;">'
                . $this->bar(self::share_of($totals['value'], $peak), $color)
                . '</td>'
                . '<td align="right" style="padding:12px 0 12px 12px;border-bottom:1px solid #eceef1;'
                . 'font-size:14px;color:#1f2328;font-weight:600;white-space:nowrap;">'
                . esc_html(hrb_format_amount($totals['value']))
                . '<div style="font-weight:400;font-size:12px;color:#6b7280;padding-top:2px;">'
                . sprintf(
                    /* translators: 1: number of bookings, 2: amount received */
                    esc_html__('%1$d booked · %2$s in', 'hourly-room-booking'),
                    (int) $totals['bookings'],
                    esc_html(hrb_format_amount($totals['collected']))
                )
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
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
            return '<tr><td colspan="3" style="padding:14px 0;color:#6b7280;font-style:italic;font-size:14px;">'
                . esc_html__('No bookings were created on this day.', 'hourly-room-booking')
                . '</td></tr>';
        }

        $html = '';
        foreach ($figures['by_payment_status'] as $status => $count) {
            $label = isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
            $color = self::status_color($status);

            // The written status is what carries the meaning; the colour only
            // repeats it, which is the rule for a reserved status palette.
            $html .= '<tr>'
                . '<td style="padding:10px 0;border-bottom:1px solid #eceef1;font-size:14px;color:#1f2328;">'
                . '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;'
                . 'background:' . $color . ';margin-right:8px;"></span>'
                . esc_html($label)
                . '</td>'
                . '<td align="right" style="padding:10px 0;border-bottom:1px solid #eceef1;'
                . 'font-size:14px;color:#1f2328;font-weight:600;">' . (int) $count . '</td>'
                . '</tr>';
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
            return '<tr><td colspan="3" style="padding:14px 0;color:#6b7280;font-style:italic;font-size:14px;">'
                . esc_html__('No bookings were created on this day.', 'hourly-room-booking')
                . '</td></tr>';
        }

        // One hue for magnitude: the rooms are not different kinds of thing,
        // they are the same measure at different sizes, so this is a sequential
        // encoding rather than a categorical one.
        $peak = 0.0;
        foreach ($figures['rooms'] as $room) {
            $peak = max($peak, (float) $room['value']);
        }

        $html = '';
        foreach ($figures['rooms'] as $room) {
            $html .= '<tr>'
                . '<td style="padding:12px 12px 12px 0;border-bottom:1px solid #eceef1;font-size:14px;'
                . 'color:#1f2328;">' . esc_html($room['name'])
                . '<div style="font-size:12px;color:#6b7280;padding-top:2px;">'
                . sprintf(
                    /* translators: 1: number of bookings, 2: hours booked */
                    esc_html__('%1$d bookings · %2$s h', 'hourly-room-booking'),
                    (int) $room['bookings'],
                    esc_html($this->format_hours($room['hours']))
                )
                . '</div></td>'
                . '<td style="padding:12px 12px;border-bottom:1px solid #eceef1;width:34%;">'
                . $this->bar(self::share_of($room['value'], $peak), '#2a78d6')
                . '</td>'
                . '<td align="right" style="padding:12px 0 12px 12px;border-bottom:1px solid #eceef1;'
                . 'font-size:14px;color:#1f2328;font-weight:600;white-space:nowrap;">'
                . esc_html(hrb_format_amount($room['value']))
                . '</td>'
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
