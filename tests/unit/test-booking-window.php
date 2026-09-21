<?php
/**
 * Tests for the booking window: the hours a booking may start in.
 *
 * "Booking Start Time" and "Booking End Time" bound when a booking may begin.
 * The end is the latest time a slot may *open* at, not the time everything
 * has to be over by: with a 23:30 end and a three-hour booking the last slot
 * offered is 23:30-02:30, running past the window and past midnight, and that
 * is the intent. Only the start is measured here.
 *
 * How long a booking runs once it has started is the duration rules' business;
 * where a room has its own bookable hours, those bound the start as well.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-booking-window.php
 *
 * @package HourlyRoomBooking
 * @since 1.12.0
 */

define('ABSPATH', __DIR__);

function add_action() { return true; }
function add_filter() { return true; }
function __($text, $domain = null) { return $text; }

function get_option($name, $default = false) { return $default; }

require_once dirname(__DIR__, 2) . '/includes/class-booking-manager.php';
require_once dirname(__DIR__, 2) . '/includes/class-room-manager.php';

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

/** May a booking start at this time, in a window of 08:00 to 23:30? */
function may_start(string $time, string $from = '08:00', string $to = '23:30'): bool {
    return HRB_Booking_Manager::is_time_within_window($time, $from, $to);
}

function room(string $from, string $to): stdClass {
    $r = new stdClass();
    $r->available_from = $from;
    $r->available_to   = $to;
    return $r;
}

function room_may_start(string $time, string $from, string $to): bool {
    return HRB_Room_Manager::getInstance()->is_start_within_availability(room($from, $to), $time);
}

// ---------------------------------------------------------------------------
// The window a booking may start in: 08:00 to 23:30
// ---------------------------------------------------------------------------

echo "\n-- inside the window --\n";

check('the opening time itself is bookable', may_start('08:00'), true);
check('the middle of the day', may_start('14:30'), true);
check('the closing time itself is bookable', may_start('23:30'), true);
check('seconds on the time do not matter', may_start('23:30:00'), true);

echo "\n-- outside the window --\n";

check('a minute before opening', may_start('07:59'), false);
check('the half hour before opening', may_start('07:30'), false);
check('a minute after the last startable time', may_start('23:31'), false);
check('the small hours', may_start('03:00'), false);
check('midnight itself', may_start('00:00'), false);

echo "\n-- the point: the end bounds the start, not the finish --\n";

// With a 23:30 end and a three-hour booking the last slot is 23:30-02:30. The
// window is asked about 23:30 and nothing else; where the booking finishes is
// the duration rules' business.
check('a 23:30 start is allowed', may_start('23:30'), true);
check('...however long it then runs', may_start('23:30'), true);
check('a window ending 20:00 still allows a 20:00 start', may_start('20:00', '08:00', '20:00'), true);
check('...but not 20:30', may_start('20:30', '08:00', '20:00'), false);

echo "\n-- midnight as an end --\n";

check('an end of 00:00 leaves the day open, not shut', may_start('23:30', '08:00', '00:00'), true);
check('an end of 24:00 reads the same way', may_start('23:30', '08:00', '24:00'), true);
check('an end of 00:00 still bars the small hours', may_start('03:00', '08:00', '00:00'), false);
check('a window of 00:00 to 00:00 is the whole day', may_start('03:00', '00:00', '00:00'), true);

echo "\n-- a window that wraps midnight --\n";

check('the evening is inside 20:00-02:00', may_start('21:00', '20:00', '02:00'), true);
check('so are the small hours', may_start('01:30', '20:00', '02:00'), true);
check('its closing time is bookable', may_start('02:00', '20:00', '02:00'), true);
check('the afternoon is not', may_start('15:00', '20:00', '02:00'), false);
// ---------------------------------------------------------------------------
// A room's own bookable hours do bound the slot
// ---------------------------------------------------------------------------

echo "\n-- per-room bookable hours --\n";

check('a room left at 00:00-00:00 takes any hour', room_may_start('03:00', '00:00:00', '00:00:00'), true);
check('a room open 08:00-23:30 takes a 23:30 start', room_may_start('23:30:00', '08:00:00', '23:30:00'), true);
check('...and what follows midnight is not its concern', room_may_start('23:00:00', '08:00:00', '23:30:00'), true);
check('a start before the room opens is refused', room_may_start('07:00:00', '08:00:00', '23:30:00'), false);
check('so is one after it closes', room_may_start('23:45:00', '08:00:00', '23:30:00'), false);
check('a room open until midnight takes a 23:30 start', room_may_start('23:30:00', '08:00:00', '00:00:00'), true);

// ---------------------------------------------------------------------------
// What a cross-midnight booking looks like to a calendar
// ---------------------------------------------------------------------------

echo "\n-- the end of a booking as a calendar datetime --\n";

function ends_at(string $date, string $start, string $end): string {
    return HRB_Booking_Manager::end_datetime($date, $start, $end);
}

check('an ordinary booking ends on its own day',
    ends_at('2026-09-21', '14:00:00', '18:00:00'), '2026-09-21T18:00:00');
check('one running past midnight ends on the next',
    ends_at('2026-09-21', '23:30:00', '05:00:00'), '2026-09-22T05:00:00');
check('one ending exactly at midnight ends on the next',
    ends_at('2026-09-21', '22:00:00', '00:00:00'), '2026-09-22T00:00:00');
check('a full 24 hours lands on the next day, same time',
    ends_at('2026-09-21', '12:00:00', '12:00:00'), '2026-09-22T12:00:00');
check('the month rolls over with the day',
    ends_at('2026-09-30', '23:00:00', '02:00:00'), '2026-10-01T02:00:00');
check('a separator other than T is honoured',
    HRB_Booking_Manager::end_datetime('2026-09-21', '23:00:00', '02:00:00', ' '), '2026-09-22 02:00:00');

// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------

echo "\n";
if ($failures > 0) {
    printf("%d FAILED\n", $failures);
    exit(1);
}

echo "ALL PASSED\n";
