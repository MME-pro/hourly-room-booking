<?php
/**
 * Tests for which bookings become no-shows.
 *
 * A booking whose time is over and whose money never arrived is a no-show: the
 * slot was held, the room stood empty and nobody came. It is not a payment the
 * business is still waiting for, and leaving it "pending" quietly inflated
 * every outstanding figure on the admin screens for ever.
 *
 * Three things have to be true together, and the interesting cases are the
 * ones where only two are:
 *
 *   - the end has gone by, not the start - a booking is not a no-show while it
 *     is still running, however late the customer is;
 *   - the money never came;
 *   - nobody has settled it already - a cancellation was called off in
 *     advance, which is a different thing from being stood up.
 *
 * The overnight case is the one worth pinning: 23:30 to 02:30 is stored on the
 * day it starts with an end earlier than its start, and read naively it looks
 * like a booking that ended twenty-one hours before it began.
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

check(
    'confirmed, unpaid, finished this morning',
    is_no_show('confirmed', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
    true
);

check(
    'never even confirmed, unpaid, finished this morning',
    is_no_show('pending', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
    true
);

check(
    'already moved on to completed, but the money never came',
    is_no_show('completed', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
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
    'ended one second ago',
    is_no_show('confirmed', 'pending', '2026-09-22', '12:59:59', '13:59:59'),
    true
);

// ---------------------------------------------------------------------------
// Overnight: the end belongs to the next day
// ---------------------------------------------------------------------------

echo "\n-- a booking that runs past midnight --\n";

check(
    'started 23:30 last night, runs to 02:30 - long over by 14:00',
    is_no_show('confirmed', 'pending', '2026-09-21', '23:30:00', '02:30:00'),
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
    is_no_show(' Confirmed ', ' PENDING ', '2026-09-22', '09:00:00', '10:00:00'),
    true
);

check(
    'padded and capitalised cancellation is still a cancellation',
    is_no_show(' Cancelled ', 'pending', '2026-09-22', '09:00:00', '10:00:00'),
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

echo "\n";
echo $failures === 0
    ? "All checks passed.\n"
    : sprintf("%d check(s) FAILED.\n", $failures);

exit($failures === 0 ? 0 : 1);
