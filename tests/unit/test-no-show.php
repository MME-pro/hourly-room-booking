<?php
/**
 * Tests for which bookings become no-shows.
 *
 * A booking whose day is over and whose money never arrived is a no-show: the
 * slot was held, the room stood empty and nobody came. It is not a payment the
 * business is still waiting for, and leaving it "pending" quietly inflated
 * every outstanding figure on the admin screens for ever.
 *
 * Three things have to be true together, and the interesting cases are the
 * ones where only two are:
 *
 *   - the booking's *day* has gone by, not merely its end time. Someone who
 *     has not turned up by 10:00 still has the rest of the day to walk in and
 *     pay, so the rule waits for midnight;
 *   - the money never came;
 *   - nobody has settled it already - a cancellation was called off in
 *     advance, which is a different thing from being stood up.
 *
 * The overnight case is the one worth pinning: 23:30 to 02:30 is stored on the
 * day it starts with an end earlier than its start, and read naively it looks
 * like a booking that ended twenty-one hours before it began. It is measured
 * from the day it actually finishes on, so it survives the whole of that day
 * too.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-no-show.php
 *
 * @package HourlyRoomBooking
 * @since 1.18.0
 */

define('ABSPATH', __DIR__);
define('HRB_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

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

/** The rules under test are static and touch nothing else, so stub the rest. */
function add_action() { return true; }
function add_filter() { return true; }
function __($text, $domain = null) { return $text; }

require_once HRB_PLUGIN_DIR . 'includes/class-status-constants.php';
require_once HRB_PLUGIN_DIR . 'includes/class-capabilities.php';
require_once HRB_PLUGIN_DIR . 'includes/class-booking-manager.php';

// "Now" for every case below: 2026-09-22 at 14:00.
const NOW = '2026-09-22 14:00:00';

function is_no_show(
    string $status,
    string $payment_status,
    string $date,
    string $start,
    string $end
): bool {
    return HRB_Booking_Manager::qualifies_as_no_show(
        $status, $payment_status, $date, $start, $end, NOW
    );
}

// ---------------------------------------------------------------------------
// The case the rule exists for
// ---------------------------------------------------------------------------

echo "\n-- a booking that is over and was never paid for --\n";

// The day a booking sits on belongs to it: someone who has not turned up by
// 10:00 still has the rest of the day to walk in and pay, so the rule waits
// for midnight rather than firing the moment the slot ended.
check(
    'confirmed, unpaid, finished this morning - the day is not over yet',
    is_no_show('confirmed', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
    false
);

check(
    'the same booking, once its day has turned over',
    is_no_show('confirmed', 'pending', '2026-09-21', '09:00:00', '10:00:00'),
    true
);

check(
    'never even confirmed, unpaid, finished yesterday',
    is_no_show('pending', 'pending', '2026-09-21', '09:00:00', '10:00:00'),
    true
);

check(
    'already moved on to completed, but the money never came',
    is_no_show('completed', 'pending', '2026-09-21', '09:00:00', '10:00:00'),
    true
);

check(
    'finished days ago',
    is_no_show('confirmed', 'pending', '2026-09-18', '09:00:00', '10:00:00'),
    true
);

// ---------------------------------------------------------------------------
// The money arrived
// ---------------------------------------------------------------------------

echo "\n-- money that arrived is not a no-show --\n";

foreach (['completed', 'refunded', 'partially_refunded', 'failed', 'cancelled', 'nil'] as $paid) {
    check(
        "payment status '{$paid}' on a finished booking",
        is_no_show('confirmed', $paid, '2026-09-22', '09:00:00', '10:00:00'),
        false
    );
}

// ---------------------------------------------------------------------------
// The time is not over
// ---------------------------------------------------------------------------

echo "\n-- a booking still ahead of the clock is nobody's no-show --\n";

check(
    'still running: 13:00 to 15:00, unpaid',
    is_no_show('confirmed', 'pending', '2026-09-22', '13:00:00', '15:00:00'),
    false
);

check(
    'later today',
    is_no_show('confirmed', 'pending', '2026-09-22', '18:00:00', '19:00:00'),
    false
);

check(
    'next week',
    is_no_show('pending', 'pending', '2026-09-29', '09:00:00', '10:00:00'),
    false
);

check(
    'ends exactly now - still running, not a no-show yet',
    is_no_show('confirmed', 'pending', '2026-09-22', '13:00:00', '14:00:00'),
    false
);

check(
    'ended one second ago - still today, so not yet',
    is_no_show('confirmed', 'pending', '2026-09-22', '12:59:59', '13:59:59'),
    false
);

check(
    'ended one second before midnight last night',
    is_no_show('confirmed', 'pending', '2026-09-21', '21:59:59', '23:59:59'),
    true
);

// ---------------------------------------------------------------------------
// Overnight: the end belongs to the next day
// ---------------------------------------------------------------------------

echo "\n-- a booking that runs past midnight --\n";

// Measured from the day it finishes on, which is the 22nd - so it is still
// live at 14:00 on the 22nd even though it ended at 02:30.
check(
    'started 23:30 on the 21st, ran to 02:30 - its day is still running',
    is_no_show('confirmed', 'pending', '2026-09-21', '23:30:00', '02:30:00'),
    false
);

check(
    'started 23:30 on the 20th, ran to 02:30 on the 21st - over',
    is_no_show('confirmed', 'pending', '2026-09-20', '23:30:00', '02:30:00'),
    true
);

check(
    'starts 23:30 tonight, runs to 02:30 tomorrow - has not happened yet',
    is_no_show('confirmed', 'pending', '2026-09-22', '23:30:00', '02:30:00'),
    false
);

// ---------------------------------------------------------------------------
// Already settled
// ---------------------------------------------------------------------------

echo "\n-- a booking somebody has already dealt with --\n";

check(
    'cancelled in advance, never paid: called off, not stood up',
    is_no_show('cancelled', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
    false
);

check(
    'already a no-show: the pass must not run over it twice',
    is_no_show('no_show', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
    false
);

// ---------------------------------------------------------------------------
// Sloppy input
// ---------------------------------------------------------------------------

echo "\n-- statuses as they come out of the database --\n";

check(
    'padded and capitalised',
    is_no_show(' Confirmed ', ' PENDING ', '2026-09-21', '09:00:00', '10:00:00'),
    true
);

check(
    'padded and capitalised cancellation is still a cancellation',
    is_no_show(' Cancelled ', 'pending', '2026-09-21', '09:00:00', '10:00:00'),
    false
);

// ---------------------------------------------------------------------------
// The SQL says the same thing as the PHP
// ---------------------------------------------------------------------------

echo "\n-- the end-time expression the pass runs in SQL --\n";

$sql = HRB_Capabilities::ended_at_sql('b');

check(
    'rolls an overnight end onto the next day',
    (bool) strpos($sql, 'DATE_ADD(b.booking_date, INTERVAL 1 DAY)'),
    true
);

check(
    'an alias cannot carry anything but a name into the query',
    HRB_Capabilities::ended_at_sql('b; DROP TABLE wp_hrb_bookings--'),
    HRB_Capabilities::ended_at_sql('bDROPTABLEwp_hrb_bookings')
);

echo "\n-- the boundary the pass actually compares against --\n";

// Verified against MySQL 8.4: for 2026-09-21 06:00-07:00 this yields
// 2026-09-22 00:00:00, and for the overnight 23:30-02:30 it yields
// 2026-09-23 00:00:00 - the same two answers becomes_past_at() gives in PHP.
$past_sql = HRB_Capabilities::becomes_past_at_sql('b');

check(
    'it is midnight after the day the booking ends on',
    strpos($past_sql, 'DATE_ADD(DATE(') !== false,
    true
);
check(
    'built on the end expression rather than a second copy of the rule',
    strpos($past_sql, HRB_Capabilities::ended_at_sql('b')) !== false,
    true
);
check(
    'it yields midnight, so INTERVAL 1 DAY is applied to a DATE',
    strpos($past_sql, 'TIMESTAMP(DATE_ADD(DATE(') === 0,
    true
);
check(
    'an alias cannot carry anything but a name into this one either',
    HRB_Capabilities::becomes_past_at_sql('b; DROP TABLE wp_hrb_bookings--'),
    HRB_Capabilities::becomes_past_at_sql('bDROPTABLEwp_hrb_bookings')
);

// ---------------------------------------------------------------------------
// The daily summary's window
//
// The pass runs hourly, the summary is daily, and what holds it to one mail a
// day is the date. The window that opens where the last one closed is what
// carries a no-show marked by hand during the day into the next morning's
// mail, rather than it being left out because the pass did not mark it.
// ---------------------------------------------------------------------------

echo "\n-- the daily no-show summary window --\n";

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

check(
    'a summary already sent today means nothing more goes out today',
    HRB_Booking_Manager::no_show_report_window('2026-09-23 00:20:00', '2026-09-23 14:20:00'),
    null
);

check(
    'once the date turns over the window opens where the last one closed',
    HRB_Booking_Manager::no_show_report_window('2026-09-23 00:20:00', '2026-09-24 00:20:00'),
    '2026-09-23 00:20:00'
);

check(
    'so a no-show marked by hand mid-afternoon is inside the next window',
    strtotime('2026-09-23 16:40:00') > strtotime(
        HRB_Booking_Manager::no_show_report_window('2026-09-23 00:20:00', '2026-09-24 00:20:00')
    ),
    true
);

check(
    'with nothing recorded yet the window opens a day back, not at the epoch',
    HRB_Booking_Manager::no_show_report_window('', '2026-09-24 00:20:00'),
    '2026-09-23 00:20:00'
);

check(
    'and a stored value that is not a time is treated the same way',
    HRB_Booking_Manager::no_show_report_window('not a date', '2026-09-24 00:20:00'),
    '2026-09-23 00:20:00'
);

check(
    'a run later the same day, after a summary went out, still sends nothing',
    HRB_Booking_Manager::no_show_report_window('2026-09-24 00:20:00', '2026-09-24 23:59:00'),
    null
);

echo "\n";
echo $failures === 0
    ? "All checks passed.\n"
    : sprintf("%d check(s) FAILED.\n", $failures);

exit($failures === 0 ? 0 : 1);
