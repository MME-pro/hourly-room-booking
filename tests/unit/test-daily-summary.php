<?php
/**
 * Tests for the daily summary schedule and email body.
 *
 * The scheduling rules carry the risk here: WP-Cron is traffic-driven, so a
 * job set for 00:00 routinely fires minutes into the new day and must still
 * report the day that closed.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-daily-summary.php
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
 */

define('ABSPATH', __DIR__);

date_default_timezone_set('UTC');

// --- WordPress stubs -------------------------------------------------------

$GLOBALS['wp_options'] = [
    'hrb_company_name' => 'Bookingsuite',
    'hrb_date_format'  => 'd.m.Y',
    'hrb_currency'     => 'EUR',
];

function add_action() { return true; }
function add_filter() { return true; }
function apply_filters($tag, $value) { return $value; }
function __($text, $domain = null) { return $text; }
function _n($single, $plural, $number, $domain = null) { return 1 === $number ? $single : $plural; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_html__($text, $domain = null) { return esc_html($text); }
function get_option($key, $default = false) { return $GLOBALS['wp_options'][$key] ?? $default; }
function get_bloginfo($what) { return 'Test Site'; }
function wp_timezone() { return new DateTimeZone('UTC'); }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function number_format_i18n($number, $decimals = 0) { return number_format((float) $number, $decimals, ',', '.'); }
function sanitize_email($email) { return trim((string) $email); }
function is_email($email) { return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL); }
function hrb_format_amount($amount) { return number_format((float) $amount, 2, ',', '.') . ' €'; }

require_once dirname(__DIR__, 2) . '/includes/class-daily-summary.php';

// --- Harness ---------------------------------------------------------------

$failures = 0;

function check(string $label, $actual, $expected): void {
    global $failures;

    $passed = $actual === $expected;
    if (!$passed) {
        $failures++;
    }

    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);

    if (!$passed) {
        printf(
            "    expected: %s\n    actual:   %s\n",
            var_export($expected, true),
            var_export($actual, true)
        );
    }
}

function check_contains(string $label, string $haystack, string $needle): void {
    global $failures;

    $passed = strpos($haystack, $needle) !== false;
    if (!$passed) {
        $failures++;
    }

    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);

    if (!$passed) {
        printf("    expected the email to contain: %s\n", $needle);
    }
}

function ts(string $datetime): int {
    return strtotime($datetime . ' UTC');
}

// ---------------------------------------------------------------------------
// Time normalisation
// ---------------------------------------------------------------------------

echo "\n-- normalize_time --\n";

check('a plain time', HRB_Daily_Summary::normalize_time('08:30'), '08:30');
check('single digit hour is padded', HRB_Daily_Summary::normalize_time('8:5'), '08:05');
check('seconds are dropped', HRB_Daily_Summary::normalize_time('18:00:00'), '18:00');
check('surrounding whitespace', HRB_Daily_Summary::normalize_time('  23:59 '), '23:59');
check('midnight', HRB_Daily_Summary::normalize_time('00:00'), '00:00');
check('an impossible hour falls back to midnight', HRB_Daily_Summary::normalize_time('25:00'), '00:00');
check('an impossible minute falls back to midnight', HRB_Daily_Summary::normalize_time('12:75'), '00:00');
check('junk falls back to midnight', HRB_Daily_Summary::normalize_time('lunchtime'), '00:00');
check('empty falls back to midnight', HRB_Daily_Summary::normalize_time(''), '00:00');

// ---------------------------------------------------------------------------
// Which day gets reported
// ---------------------------------------------------------------------------

echo "\n-- resolve_summary_date (midnight send) --\n";

check(
    'fired exactly at midnight reports the day that just ended',
    HRB_Daily_Summary::resolve_summary_date('00:00', ts('2026-09-04 00:00:00')),
    '2026-09-03'
);

// WP-Cron is traffic-driven: a 00:00 job commonly runs minutes late. It must
// still report the closed day, not the handful of minutes of the new one.
check(
    'fired 7 minutes late still reports the day that ended',
    HRB_Daily_Summary::resolve_summary_date('00:00', ts('2026-09-04 00:07:00')),
    '2026-09-03'
);

check(
    'fired hours late (quiet site) still reports the day that ended',
    HRB_Daily_Summary::resolve_summary_date('00:00', ts('2026-09-04 05:30:00')),
    '2026-09-03'
);

check(
    'across a month boundary',
    HRB_Daily_Summary::resolve_summary_date('00:00', ts('2026-10-01 00:02:00')),
    '2026-09-30'
);

check(
    'across a year boundary',
    HRB_Daily_Summary::resolve_summary_date('00:00', ts('2027-01-01 00:01:00')),
    '2026-12-31'
);

echo "\n-- resolve_summary_date (later send times) --\n";

check(
    'an 18:00 send covers the current day',
    HRB_Daily_Summary::resolve_summary_date('18:00', ts('2026-09-03 18:00:00')),
    '2026-09-03'
);

check(
    'an 18:00 send running late still covers the current day',
    HRB_Daily_Summary::resolve_summary_date('18:00', ts('2026-09-03 18:20:00')),
    '2026-09-03'
);

check(
    'a 23:59 send covers the current day',
    HRB_Daily_Summary::resolve_summary_date('23:59', ts('2026-09-03 23:59:30')),
    '2026-09-03'
);

check(
    'before the send time, the previous run is what counts',
    HRB_Daily_Summary::resolve_summary_date('18:00', ts('2026-09-04 09:00:00')),
    '2026-09-03'
);

// ---------------------------------------------------------------------------
// When the next run happens
// ---------------------------------------------------------------------------

echo "\n-- next_run_timestamp --\n";

$utc = new DateTimeZone('UTC');

check(
    'later today when the send time has not passed',
    HRB_Daily_Summary::next_run_timestamp('18:00', ts('2026-09-03 09:00:00'), $utc),
    ts('2026-09-03 18:00:00')
);

check(
    'tomorrow when the send time has passed',
    HRB_Daily_Summary::next_run_timestamp('18:00', ts('2026-09-03 18:30:00'), $utc),
    ts('2026-09-04 18:00:00')
);

check(
    'exactly at the send time rolls to tomorrow',
    HRB_Daily_Summary::next_run_timestamp('18:00', ts('2026-09-03 18:00:00'), $utc),
    ts('2026-09-04 18:00:00')
);

check(
    'midnight rolls to the next day',
    HRB_Daily_Summary::next_run_timestamp('00:00', ts('2026-09-03 12:00:00'), $utc),
    ts('2026-09-04 00:00:00')
);

// A configured time is honoured in the site's own zone, not UTC.
$berlin = new DateTimeZone('Europe/Berlin');
check(
    'the send time is interpreted in the plugin timezone',
    HRB_Daily_Summary::next_run_timestamp('00:00', ts('2026-09-03 12:00:00'), $berlin),
    ts('2026-09-03 22:00:00') // 2026-09-04 00:00 Berlin (CEST, UTC+2)
);

// ---------------------------------------------------------------------------
// The email body
// ---------------------------------------------------------------------------

echo "\n-- render_html --\n";

$summary = HRB_Daily_Summary::getInstance();

$figures = [
    'date'              => '2026-09-03',
    'total'             => 7,
    'by_status'         => ['confirmed' => 5, 'pending' => 1, 'cancelled' => 1],
    'hours'             => 18.0,
    'value'             => 1420.50,
    'rooms'             => [
        ['name' => 'Room 2', 'bookings' => 4, 'hours' => 11.0, 'value' => 900.00],
        ['name' => 'Room 3', 'bookings' => 2, 'hours' => 7.0,  'value' => 520.50],
    ],
    'collected'         => 1100.00,
    'collected_count'   => 3,
    'outstanding'       => 320.50,
    'created'           => 4,
    'created_value'     => 760.00,
    'cancellation_fees' => 15.00,
];

$html = $summary->render_html($figures);

check_contains('the date it covers', $html, '03.09.2026');
check_contains('the booking count', $html, '>7<');
check_contains('the hours booked', $html, '>18<');
check_contains('the booking value', $html, '1.420,50 €');
check_contains('payments received', $html, '1.100,00 €');
check_contains('the payment count', $html, '(3)');
check_contains('the outstanding amount', $html, '320,50 €');
check_contains('new bookings', $html, '760,00 €');
check_contains('the first room', $html, 'Room 2');
check_contains('the second room', $html, 'Room 3');
check_contains('a per-room value', $html, '900,00 €');
check_contains('the status breakdown', $html, 'Confirmed');
check_contains('the cancellation fee line', $html, '15,00 €');
check_contains('the company name', $html, 'Bookingsuite');
check('the mail is a complete document', substr($html, 0, 15), '<!DOCTYPE html>');

// A quiet day must still produce a readable mail rather than an empty shell.
$empty_day = [
    'date'              => '2026-09-03',
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

$empty_html = $summary->render_html($empty_day);

check_contains('a day with no bookings says so', $empty_html, 'No bookings for this day.');
check_contains('...and still shows the date', $empty_html, '03.09.2026');
check(
    'no cancellation-fee line when there were none',
    strpos($empty_html, 'Cancellation fees') === false,
    true
);

// Room names are escaped, not injected into the markup.
$injected = $summary->render_html(array_merge($empty_day, [
    'rooms' => [['name' => '<script>alert(1)</script>', 'bookings' => 1, 'hours' => 2.0, 'value' => 10.0]],
]));

check('a room name is escaped', strpos($injected, '<script>alert(1)</script>') === false, true);
check_contains('...and shown escaped instead', $injected, '&lt;script&gt;');

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
