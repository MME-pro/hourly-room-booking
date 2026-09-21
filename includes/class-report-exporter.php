<?php
/**
 * CSV exports for the Payments and Reports screens
 *
 * Both screens had an export button whose link went nowhere: it asked
 * admin-ajax for `action=export_payments` and `action=export_report`, and
 * WordPress only dispatches an action it has a `wp_ajax_{action}` hook for.
 * With no hook, admin-ajax answers with its "nothing matched" body - the bare
 * `0` that arrived as a one-byte download.
 *
 * The export reads the same query string the screen was filtered by, so what
 * downloads is what was on screen rather than everything in the table.
 *
 * @package HourlyRoomBooking
 * @since 1.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Report_Exporter {

    private static $instance = null;

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_hrb_export_payments', array($this, 'export_payments'));
        add_action('wp_ajax_hrb_export_report', array($this, 'export_report'));
    }

    /**
     * The dates a report range covers
     *
     * The Reports screen works this out to draw its figures and the export has
     * to land on exactly the same two dates, so the rule lives in one place and
     * both ask it. A custom range with either end missing falls back to the
     * current month, which is what the screen has always done.
     *
     * @since 1.13.0
     * @param string   $range        7_days, 30_days, 90_days, this_month,
     *                               last_month, this_year or custom
     * @param string   $custom_start Y-m-d, only read for the custom range
     * @param string   $custom_end   Y-m-d, only read for the custom range
     * @param int|null $now          Timestamp to reckon from; defaults to now
     * @return array Start and end date, Y-m-d
     */
    public static function date_range($range, $custom_start = '', $custom_end = '', $now = null) {
        $now = $now === null ? time() : $now;

        switch ($range) {
            case '7_days':
                return [date('Y-m-d', strtotime('-7 days', $now)), date('Y-m-d', $now)];

            case '30_days':
                return [date('Y-m-d', strtotime('-30 days', $now)), date('Y-m-d', $now)];

            case '90_days':
                return [date('Y-m-d', strtotime('-90 days', $now)), date('Y-m-d', $now)];

            case 'last_month':
                // Stepping back a day from the 1st lands in the previous month
                // whatever its length; "-1 month" from the 31st does not.
                $in_last_month = strtotime('-1 day', strtotime(date('Y-m-01', $now)));
                return [date('Y-m-01', $in_last_month), date('Y-m-t', $in_last_month)];

            case 'this_year':
                return [date('Y-01-01', $now), date('Y-12-31', $now)];

            case 'custom':
                if ($custom_start !== '' && $custom_end !== '') {
                    return [$custom_start, $custom_end];
                }
                // An incomplete custom range falls through to the current month.

            case 'this_month':
            default:
                return [date('Y-m-01', $now), date('Y-m-t', $now)];
        }
    }

    /**
     * Refuse anyone who may not take data out of the plugin
     *
     * An export carries money, so it belongs to the roles allowed to see it.
     *
     * @since 1.13.0
     * @param string $view_capability Capability for the screen being exported
     */
    private function authorise($view_capability) {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can($view_capability) || !current_user_can('hrb_export_data')) {
            wp_die(esc_html__('Insufficient permissions', 'hourly-room-booking'), 403);
        }
    }

    /**
     * Send rows as a CSV download and stop
     *
     * The byte-order mark is what makes Excel read the file as UTF-8; without
     * it a euro sign or an umlaut arrives as mojibake, which is how a broken
     * export usually looks to the person opening it.
     *
     * @since 1.13.0
     * @param string $filename Suggested download name
     * @param array  $rows     List of rows, each a flat array of values
     */
    private function send_csv($filename, array $rows) {
        // Anything already buffered - a stray notice, whitespace from an
        // include - would land inside the file and break it.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        foreach ($rows as $row) {
            fputcsv($output, (array) $row, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * The filters the Payments screen was showing, read back off the query string
     *
     * @since 1.13.0
     * @return array
     */
    private function payment_filters() {
        return [
            'status'         => sanitize_text_field($_GET['status'] ?? ''),
            'payment_method' => sanitize_text_field($_GET['payment_method'] ?? ''),
            'date_from'      => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to'        => sanitize_text_field($_GET['date_to'] ?? ''),
            'search'         => sanitize_text_field($_GET['s'] ?? ''),
        ];
    }

    /**
     * Download the payments list, filtered as the screen was
     *
     * @since 1.13.0
     */
    public function export_payments() {
        $this->authorise('hrb_view_payments');

        $rows = HRB_Payment_Manager::getInstance()->export_payments($this->payment_filters());

        $this->send_csv('payments_' . date('Y-m-d') . '.csv', $rows);
    }

    /**
     * Download the figures behind the Reports screen for its selected range
     *
     * @since 1.13.0
     */
    public function export_report() {
        $this->authorise('hrb_view_reports');

        $dates = self::date_range(
            sanitize_text_field($_GET['range'] ?? 'this_month'),
            sanitize_text_field($_GET['start_date'] ?? ''),
            sanitize_text_field($_GET['end_date'] ?? '')
        );

        $this->send_csv(
            'report_' . $dates[0] . '_to_' . $dates[1] . '.csv',
            $this->report_rows($dates[0], $dates[1])
        );
    }

    /**
     * The report as rows: a summary block, then one row per room, then per day
     *
     * @since 1.13.0
     * @param string $start_date Y-m-d
     * @param string $end_date   Y-m-d
     * @return array
     */
    private function report_rows($start_date, $end_date) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) AS total_bookings,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_bookings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
                COUNT(DISTINCT customer_id) AS unique_customers,
                COALESCE(AVG(total_hours), 0) AS avg_duration,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(AVG(total_amount), 0) AS avg_booking_value
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date BETWEEN %s AND %s
        ", $start_date, $end_date));

        $rows = [];
        $rows[] = [__('Report period', 'hourly-room-booking'), $start_date, $end_date];
        $rows[] = [];
        $rows[] = [__('Summary', 'hourly-room-booking')];
        $rows[] = [__('Metric', 'hourly-room-booking'), __('Value', 'hourly-room-booking')];
        $rows[] = [__('Total bookings', 'hourly-room-booking'), (int) ($stats->total_bookings ?? 0)];
        $rows[] = [hrb_get_booking_status_label('confirmed'), (int) ($stats->confirmed_bookings ?? 0)];
        $rows[] = [hrb_get_booking_status_label('pending'), (int) ($stats->pending_bookings ?? 0)];
        $rows[] = [hrb_get_booking_status_label('completed'), (int) ($stats->completed_bookings ?? 0)];
        $rows[] = [hrb_get_booking_status_label('cancelled'), (int) ($stats->cancelled_bookings ?? 0)];
        $rows[] = [__('Unique customers', 'hourly-room-booking'), (int) ($stats->unique_customers ?? 0)];
        $rows[] = [__('Average duration (hours)', 'hourly-room-booking'), number_format((float) ($stats->avg_duration ?? 0), 2, '.', '')];
        $rows[] = [__('Total revenue', 'hourly-room-booking'), number_format((float) ($stats->total_revenue ?? 0), 2, '.', '')];
        $rows[] = [__('Average booking value', 'hourly-room-booking'), number_format((float) ($stats->avg_booking_value ?? 0), 2, '.', '')];

        $per_room = $wpdb->get_results($wpdb->prepare("
            SELECT r.name AS room_name,
                   COUNT(b.id) AS booking_count,
                   COALESCE(SUM(b.total_amount), 0) AS revenue
            FROM {$wpdb->prefix}hrb_rooms r
            LEFT JOIN {$wpdb->prefix}hrb_bookings b
                   ON b.room_id = r.id AND b.booking_date BETWEEN %s AND %s
            GROUP BY r.id, r.name
            ORDER BY booking_count DESC
        ", $start_date, $end_date));

        $rows[] = [];
        $rows[] = [__('Per room', 'hourly-room-booking')];
        $rows[] = [__('Room', 'hourly-room-booking'), __('Bookings', 'hourly-room-booking'), __('Revenue', 'hourly-room-booking')];
        foreach ($per_room as $room) {
            $rows[] = [$room->room_name, (int) $room->booking_count, number_format((float) $room->revenue, 2, '.', '')];
        }

        $per_day = $wpdb->get_results($wpdb->prepare("
            SELECT booking_date,
                   COUNT(*) AS booking_count,
                   COALESCE(SUM(total_amount), 0) AS revenue
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date BETWEEN %s AND %s
            GROUP BY booking_date
            ORDER BY booking_date
        ", $start_date, $end_date));

        $rows[] = [];
        $rows[] = [__('Per day', 'hourly-room-booking')];
        $rows[] = [__('Date', 'hourly-room-booking'), __('Bookings', 'hourly-room-booking'), __('Revenue', 'hourly-room-booking')];
        foreach ($per_day as $day) {
            $rows[] = [$day->booking_date, (int) $day->booking_count, number_format((float) $day->revenue, 2, '.', '')];
        }

        return $rows;
    }
}
