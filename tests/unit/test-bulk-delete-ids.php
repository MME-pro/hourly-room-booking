<?php
/**
 * Tests for HRB_Booking_Manager::parse_booking_id_list().
 *
 * This is the gate between the bookings list table's bulk-delete checkboxes
 * and delete_booking(), so it has to reject anything that is not a real
 * booking ID and never hand the same ID over twice.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-bulk-delete-ids.php
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
 */

define('ABSPATH', __DIR__);

// class-booking-manager.php only declares a class; these are the WordPress
// functions it touches while being loaded.
function add_action() { return true; }
function add_filter() { return true; }
function __($text, $domain = null) { return $text; }

require_once dirname(__DIR__, 2) . '/includes/class-booking-manager.php';

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

function parse($raw): array {
    return HRB_Booking_Manager::parse_booking_id_list($raw);
}

echo "\n-- the normal case --\n";

check('a plain list', parse('12,13,14'), [12, 13, 14]);
check('a single id', parse('7'), [7]);
check('ten of twenty selected', parse('1,2,3,4,5,6,7,8,9,10'), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
check('order is preserved', parse('30,10,20'), [30, 10, 20]);

echo "\n-- nothing selected --\n";

check('empty string', parse(''), []);
check('separators only', parse(',,,'), []);
check('whitespace only', parse('  '), []);

echo "\n-- junk is dropped --\n";

check('whitespace around ids', parse(' 12 , 13 '), [12, 13]);
check('empty entries between ids', parse('12,,13'), [12, 13]);
check('non-numeric entries', parse('12,abc,13'), [12, 13]);
check('a zero id', parse('0,12'), [12]);
check('a negative id', parse('-5,12'), [12]);
check('all junk yields nothing', parse('abc,-1,0'), []);
check('a trailing comma', parse('12,13,'), [12, 13]);

echo "\n-- a booking is never deleted twice --\n";

check('exact duplicates collapse', parse('12,12,13'), [12, 13]);
check('duplicates with whitespace collapse', parse('12, 12 ,13'), [12, 13]);
check('the first position is kept', parse('13,12,13'), [13, 12]);

echo "\n-- array input --\n";

check('an array of strings', parse(['12', '13']), [12, 13]);
check('an array with junk', parse(['12', '', 'abc', '13']), [12, 13]);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
