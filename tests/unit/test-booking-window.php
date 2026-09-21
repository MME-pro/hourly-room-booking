<?php
/**
 * Tests for the booking hours: when a booking may be taken.
 *
 * "Booking Opening Time" and "Booking Closing Time" are an office's opening
 * hours. They say when a booking may be *placed*, and nothing whatever about
 * which slot is being booked. A customer who walks in at 23:00, while the desk
 * is open, may book a room for 05:00 tomorrow; what is refused is taking a
 * booking at 23:45, when the place is shut.
 *
 * The slot itself is bounded only by the duration rules and, where a room has
 * them, that room's own bookable hours.
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

// now_time() reads the plugin's timezone setting; the tests pin it so the
// assertion is about the rule, not about where this machine happens to be.
$GLOBALS['hrb_test_timezone'] = 'Europe/Berlin';
function get_option($name, $default = false) {
    return 'hrb_timezone' === $name ? $GLOBALS['hrb_test_timezone'] : $default;
}

function looks_like_a_time($value): bool {
    return is_string($value) && 5 === strlen($value) && ':' === substr($value, 2, 1);
}

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

/** Is the desk open at this time of day, for an office running 09:00-23:00? */
function open_at(string $time, string $from = '09:00', string $to = '23:00'): bool {
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
// Opening hours: 09:00 to 23:00
// ---------------------------------------------------------------------------

echo "\n-- while the desk is open --\n";

check('the minute it opens', open_at('09:00'), true);
check('the middle of the day', open_at('14:30'), true);
check('the minute it closes still counts as open', open_at('23:00'), true);
check('seconds on the time do not matter', open_at('23:00:00'), true);

echo "\n-- once it is shut --\n";

check('a minute before opening', open_at('08:59'), false);
check('the small hours', open_at('03:00'), false);
check('a minute after closing', open_at('23:01'), false);
check('midnight', open_at('00:00'), false);

echo "\n-- the point: the slot booked is none of its business --\n";

// The window is asked about the clock, never about the booking. A customer at
// the desk at 23:00 booking a room for 05:00 tomorrow is the case this exists
// for, and the only question asked is "is it 23:00 or earlier".
check('taking a booking at 23:00 is allowed', open_at('23:00'), true);
check('...whatever slot that booking is for', open_at('23:00'), true);
check('taking one at 23:45 is not', open_at('23:45'), false);
check('nor at 05:00, even to book the 05:00 slot', open_at('05:00'), false);

echo "\n-- midnight as a closing time --\n";

check('closing at 00:00 keeps the desk open late', open_at('23:45', '09:00', '00:00'), true);
check('...but not in the small hours', open_at('03:00', '09:00', '00:00'), false);
check('24:00 reads the same way', open_at('23:45', '09:00', '24:00'), true);
check('00:00 to 00:00 is open around the clock', open_at('03:00', '00:00', '00:00'), true);

echo "\n-- a desk that is open past midnight --\n";

check('the evening is inside 20:00-02:00', open_at('21:00', '20:00', '02:00'), true);
check('so are the small hours', open_at('01:30', '20:00', '02:00'), true);
check('its closing minute counts', open_at('02:00', '20:00', '02:00'), true);
check('the afternoon does not', open_at('15:00', '20:00', '02:00'), false);

echo "\n-- local time, not the server's --\n";

// A server on UTC would otherwise close a Berlin desk an hour or two early.
check(
    'it is the plugin timezone that is read, not the server clock',
    HRB_Booking_Manager::now_time(),
    (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('H:i')
);

// A timezone nobody can resolve must not take bookings down with it.
$GLOBALS['hrb_test_timezone'] = 'Not/AZone';
check('an unusable timezone still yields a time', looks_like_a_time(HRB_Booking_Manager::now_time()), true);
$GLOBALS['hrb_test_timezone'] = 'Europe/Berlin';

check('now_time() gives H:i', looks_like_a_time(HRB_Booking_Manager::now_time()), true);

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
