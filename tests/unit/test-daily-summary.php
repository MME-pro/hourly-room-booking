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
define('HRB_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

date_default_timezone_set('UTC');

// No database here, so the template lookup finds nothing and the class falls
// back to the bundled copy — which is exactly what these tests exercise.
class HRB_Test_WPDB {
    public $prefix = 'wp_';
    public function prepare($query) { return $query; }
    public function get_row($query) { return null; }
}

$GLOBALS['wpdb'] = new HRB_Test_WPDB();

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
function esc_url($url) { return (string) $url; }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function wp_strip_all_tags($text) { return trim(strip_tags((string) $text)); }
function hrb_get_payment_method_label($method) {
    $labels = ['paypal' => 'PayPal', 'onsite' => 'On-site Payment', 'cash' => 'Cash', 'bank_transfer' => 'Bank Transfer'];
    return $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

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
// The times offered on the settings screen
// ---------------------------------------------------------------------------

echo "\n-- send_time_choices --\n";

$choices = HRB_Daily_Summary::send_time_choices('00:00');

check('half-hourly across the day', count($choices), 48);
check('starts at midnight', $choices[0], '00:00');
check('ends at half past eleven', end($choices), '23:30');

// The whole point: <input type="time"> renders in the browser's locale and
// shows AM/PM on an en-US machine whatever the site language is. A select of
// 24-hour strings is the same everywhere.
check(
    'every option is 24-hour',
    (bool) preg_grep('/[ap]\.?m\.?/i', $choices),
    false
);

check(
    'the afternoon is written as 13:00, not 1:00',
    in_array('13:00', $choices, true) && in_array('23:00', $choices, true),
    true
);

// A site that set 18:05 before this was a select must not have it silently
// rounded away the next time somebody opens the settings screen.
$offgrid = HRB_Daily_Summary::send_time_choices('18:05');

check('an off-grid time is kept', in_array('18:05', $offgrid, true), true);
check('and nothing else is lost', count($offgrid), 49);

$at = array_search('18:05', $offgrid, true);
check('it is sorted into place', [$offgrid[$at - 1], $offgrid[$at + 1]], ['18:00', '18:30']);

// A time already on the grid must not be duplicated.
check('a time already offered is not added twice', count(HRB_Daily_Summary::send_time_choices('18:00')), 48);

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
// The email body — rendered from the branded daily_summary_admin template
// ---------------------------------------------------------------------------

echo "\n-- the bundled template --\n";

$bundled = HRB_Daily_Summary::bundled_template();

check('the summary template is bundled', isset($bundled['html_content']), true);

// These used to assert on CSS class names, which said nothing about whether
// the mail was any good and broke the moment the template was redesigned.
// What matters is that it is a whole document carrying every figure.
check_contains('it is a complete document', $bundled['html_content'], '<!DOCTYPE html>');
check_contains('it carries the company logo slot', $bundled['html_content'], '{company_logo_html}');
check_contains('it names the company in the footer', $bundled['html_content'], '{company_name}');

foreach ([
    '{summary_date}',
    '{total_bookings}',
    '{hours_booked}',
    '{payments_received}',
    '{pending_cancellation_fees}',
    '{outstanding_bookings}',
    '{payment_method_rows}',
    '{payment_status_rows}',
    '{rooms_rows}',
] as $token) {
    check_contains("it carries {$token}", $bundled['html_content'], $token);
}

// Inline styles rather than a <style> block for the parts that must survive:
// Outlook and most webmail strip the head, and the mail is read on phones.
check_contains('the figures are styled inline', $bundled['html_content'], 'font-size:26px');
check_contains('the money owed is split in two', $bundled['html_content'], 'Offene Stornogeb');
check_contains('and named as such', $bundled['html_content'], 'ohne Stornogeb');

echo "\n-- render_html --\n";

$summary = HRB_Daily_Summary::getInstance();

$figures = [
    'date'              => '2026-09-04',
    'total'             => 4,
    'by_status'         => ['confirmed' => 2, 'pending' => 1, 'cancelled' => 1],
    'by_payment_status' => ['paid' => 1, 'pending' => 2, 'cancelled' => 1],
    'hours'             => 7.0,
    'value'             => 260.00,
    'rooms'             => [
        ['name' => 'Room 2', 'bookings' => 2, 'hours' => 5.0, 'value' => 200.00],
        ['name' => 'Room 3', 'bookings' => 1, 'hours' => 2.0, 'value' => 60.00],
    ],
    'collected'         => 120.00,
    'collected_count'   => 1,
    'outstanding'       => 80.00,
    'cancellation_fees' => 0.0,
    'by_payment_method' => [
        'onsite' => ['bookings' => 2, 'value' => 160.00, 'collected' => 120.00],
        'paypal' => ['bookings' => 2, 'value' => 100.00, 'collected' => 0.00],
    ],
    'channels'          => [
        'onsite' => ['bookings' => 2, 'value' => 160.00, 'collected' => 120.00],
        'paypal' => ['bookings' => 2, 'value' => 100.00, 'collected' => 0.00],
        'other'  => ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0],
    ],
];

$html = $summary->render_html($figures);

check_contains('the date it covers', $html, '04.09.2026');
check_contains('the number of bookings created', $html, '>4<');
check_contains('the money received', $html, '120,00 €');
check_contains('the hours booked', $html, '7 Std.');
check_contains('payments received', $html, '120,00 €');
check('the outstanding amount is split, not shown whole', strpos($html, '80,00 €') !== false, false);
check_contains('the first room', $html, 'Room 2');
check_contains('the second room', $html, 'Room 3');
check_contains('a per-room value', $html, '200,00 €');
check_contains('the payment-status breakdown', $html, 'Paid');
check_contains('the company name', $html, 'Bookingsuite');
check('every placeholder was filled', preg_match('/\{[a-z_]+\}/', $html), 0);
check('the mail is a complete document', substr($html, 0, 15), '<!DOCTYPE html>');

echo "\n-- render_subject --\n";

$subject = $summary->render_subject($figures);
check_contains('the subject carries the date', $subject, '04.09.2026');
check_contains('the subject carries the company', $subject, 'Bookingsuite');
check('the subject has no placeholders left', preg_match('/\{[a-z_]+\}/', $subject), 0);

// ---------------------------------------------------------------------------
// On-site money against PayPal money
// ---------------------------------------------------------------------------

echo "\n-- payment_channel --\n";

check('an on-site booking', HRB_Daily_Summary::payment_channel('onsite'), 'onsite');
check('cash is on-site money too', HRB_Daily_Summary::payment_channel('cash'), 'onsite');
check('PayPal is its own channel', HRB_Daily_Summary::payment_channel('paypal'), 'paypal');
check('case does not matter', HRB_Daily_Summary::payment_channel('PayPal'), 'paypal');
check('nor does stray whitespace', HRB_Daily_Summary::payment_channel(' onsite '), 'onsite');
check('a bank transfer is neither', HRB_Daily_Summary::payment_channel('bank_transfer'), 'other');
check('an empty method is neither', HRB_Daily_Summary::payment_channel(''), 'other');
check('nor a method the plugin has never heard of', HRB_Daily_Summary::payment_channel('sofort'), 'other');

// ---------------------------------------------------------------------------
// The colours and proportions the charts are drawn from
// ---------------------------------------------------------------------------

echo "\n-- chart colours --\n";

// Colour follows the method, never its position in the table: a day where one
// method drops out must not repaint the others.
check('on site is always the same hue', HRB_Daily_Summary::method_color('onsite'), '#2a78d6');
check('PayPal is always the same hue', HRB_Daily_Summary::method_color('paypal'), '#eb6834');
check('cash has its own', HRB_Daily_Summary::method_color('cash'), '#1baf7a');
check('case does not matter', HRB_Daily_Summary::method_color('PayPal'), '#eb6834');

// A fifth method is where a categorical palette stops being readable, so
// anything unrecognised is deliberately neutral rather than a generated hue.
check('an unknown method is neutral', HRB_Daily_Summary::method_color('sofort'), '#9aa3ad');

// Every method must be distinguishable from every other one on the page.
$hues = array_map(
    ['HRB_Daily_Summary', 'method_color'],
    ['onsite', 'paypal', 'cash', 'bank_transfer']
);
check('no two methods share a colour', count(array_unique($hues)), 4);

// Status colours are reserved and must not impersonate a method.
$states = array_map(
    ['HRB_Daily_Summary', 'status_color'],
    ['confirmed', 'pending', 'cancelled']
);
check('no status borrows a method colour', array_intersect($states, $hues), []);
check('confirmed reads as good', HRB_Daily_Summary::status_color('confirmed'), '#0ca30c');
check('cancelled reads as critical', HRB_Daily_Summary::status_color('cancelled'), '#d03b3b');

echo "\n-- bar proportions --\n";

check('half of a total', HRB_Daily_Summary::share_of(50, 100), 50.0);
check('all of it', HRB_Daily_Summary::share_of(100, 100), 100.0);
check('none of it', HRB_Daily_Summary::share_of(0, 100), 0.0);

// A day with no revenue must not divide by zero or draw a full bar.
check('nothing over nothing is nothing', HRB_Daily_Summary::share_of(0, 0), 0.0);
check('something over nothing is still nothing', HRB_Daily_Summary::share_of(40, 0), 0.0);

// A refund could push a part negative; a bar cannot run backwards.
check('a negative part clamps to zero', HRB_Daily_Summary::share_of(-10, 100), 0.0);
check('a part larger than the whole clamps to full', HRB_Daily_Summary::share_of(150, 100), 100.0);

echo "\n-- the split in the email --\n";

// Assert on the figures and labels the reader sees, not on the markup around
// them — pinning table cells is what made these break on every redesign.
check_contains('the money taken on site', $html, '160,00 €');
check_contains('the money taken through PayPal', $html, '100,00 €');
check_contains('a breakdown by payment method', $html, 'Zahlungsart');
check_contains('...naming the on-site method', $html, 'On-site Payment');
check_contains('...and PayPal', $html, 'PayPal');

// Money owed is shown as two separate figures - a room somebody still has to
// pay for is chased differently from a penalty on a booking that is gone - so
// the mail must never present them as one lump.
check_contains('outstanding booking money has its own card', $html, 'ohne Stornogeb');
check_contains('and outstanding fees theirs', $html, 'Offene Stornogeb');

// Every method carries its own colour, chosen by the method and not by where
// it happens to land in the table.
check_contains('the on-site colour is on the page', $html, HRB_Daily_Summary::method_color('onsite'));
check_contains('so is the PayPal colour', $html, HRB_Daily_Summary::method_color('paypal'));

// The headline figures are what the team reads first, so the split has to
// reconcile with them rather than being collected a second, different way.
$channel_bookings = array_sum(array_column($figures['channels'], 'bookings'));
$channel_value    = array_sum(array_column($figures['channels'], 'value'));

check('the channels account for every booking', $channel_bookings, $figures['total']);
check('the channels account for the whole value', $channel_value, (float) $figures['value']);

echo "\n-- a site that has never taken a PayPal payment --\n";

$cash_only = array_merge($figures, [
    'by_payment_method' => ['cash' => ['bookings' => 3, 'value' => 90.00, 'collected' => 90.00]],
    'channels'          => [
        'onsite' => ['bookings' => 3, 'value' => 90.00, 'collected' => 90.00],
        'paypal' => ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0],
        'other'  => ['bookings' => 0, 'value' => 0.0, 'collected' => 0.0],
    ],
]);

$cash_html = $summary->render_html($cash_only);

// The method table lists only what was actually used, so a cash-only day gets
// no empty PayPal row to read past.
check(
    'no empty PayPal row is invented',
    substr_count($cash_html, '>PayPal<'),
    0
);
check_contains('and the cash row is there', $cash_html, 'Cash');

// The four cards are always present, whatever the day held, so the mail has
// the same shape every morning.
foreach (['Neue Buchungen', 'Zahlungseingang', 'Offene Stornogeb', 'Offener Betrag'] as $card) {
    check_contains("the {$card} card is there on a quiet day", $cash_html, $card);
}

echo "\n-- figures a filter has trimmed --\n";

// hrb_daily_summary_figures lets a site rewrite the array; a missing key must
// not take down the one mail nobody is watching being sent.
$trimmed = $figures;
unset($trimmed['channels'], $trimmed['by_payment_method']);

$trimmed_html = $summary->render_html($trimmed);

check('the mail still renders', $trimmed_html !== '', true);
check('with no placeholders left behind', preg_match('/\{[a-z_]+\}/', $trimmed_html), 0);
check_contains('and the bar falls back to an empty track', $trimmed_html, '#eceef1');

echo "\n-- a day with nothing created --\n";

$empty_day = [
    'date'              => '2026-09-04',
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

$empty_html = $summary->render_html($empty_day);

check_contains('says so instead of rendering an empty table', $empty_html, 'No bookings were created on this day.');
check_contains('...and still shows the date', $empty_html, '04.09.2026');
check('...with no placeholders left over', preg_match('/\{[a-z_]+\}/', $empty_html), 0);

echo "\n-- escaping --\n";

$injected = $summary->render_html(array_merge($empty_day, [
    'rooms' => [['name' => '<script>alert(1)</script>', 'bookings' => 1, 'hours' => 2.0, 'value' => 10.0]],
]));

check('a room name is escaped', strpos($injected, '<script>alert(1)</script>') === false, true);
check_contains('...and shown escaped instead', $injected, '&lt;script&gt;');

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
