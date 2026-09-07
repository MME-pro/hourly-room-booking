<?php
/**
 * Tests for the room-availability rule behind the booking form's room dropdown.
 *
 * Moving a booking to another room has to respect that room's own diary. The
 * dropdown asks HRB_Room_Manager for a verdict per room; this pins down what
 * each combination of "open at this time / already booked / under maintenance"
 * means, so the form can never offer a room the save would reject.
 *
 * The database side (conflicts, cooldown, per-room hours) was exercised against
 * the real schema; only the decision itself is pinned here.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-room-availability.php
 *
 * @package HourlyRoomBooking
 * @since 1.7.3
 */

define('ABSPATH', __DIR__);

function add_action() { return true; }
function add_filter() { return true; }
function __($text, $domain = null) { return $text; }

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

function verdict(bool $in_window, bool $booked, bool $locked): array {
    return HRB_Room_Manager::describe_room_availability($in_window, $booked, $locked);
}

// ---------------------------------------------------------------------------
// A room the booking can move into
// ---------------------------------------------------------------------------

echo "\n-- selectable --\n";

check(
    'open, empty and unlocked',
    verdict(true, false, false),
    ['available' => true, 'reason' => 'free']
);

// An admin sets the maintenance locks themselves, so a lock is a warning
// rather than a barrier — but it must still be said out loud.
check(
    'under a maintenance lock: selectable, but flagged',
    verdict(true, false, true),
    ['available' => true, 'reason' => 'locked']
);

// ---------------------------------------------------------------------------
// A room the booking cannot move into
// ---------------------------------------------------------------------------

echo "\n-- blocked --\n";

check(
    'something else is booked in it',
    verdict(true, true, false),
    ['available' => false, 'reason' => 'booked']
);

check(
    'the room is closed at that time of day',
    verdict(false, false, false),
    ['available' => false, 'reason' => 'outside_hours']
);

// Closed wins over booked: changing the booking's room would not help, the
// admin has to change the time. Reporting "already booked" would send them
// looking for the wrong fix.
check(
    'closed and booked reports the closure',
    verdict(false, true, false),
    ['available' => false, 'reason' => 'outside_hours']
);

check(
    'closed and locked still reports the closure',
    verdict(false, false, true),
    ['available' => false, 'reason' => 'outside_hours']
);

check(
    'booked wins over locked',
    verdict(true, true, true),
    ['available' => false, 'reason' => 'booked']
);

// ---------------------------------------------------------------------------
// Every verdict has to be one the form knows how to label
// ---------------------------------------------------------------------------

echo "\n-- the form can label every outcome --\n";

$known = ['free', 'locked', 'booked', 'outside_hours'];
$seen  = [];

foreach ([true, false] as $in_window) {
    foreach ([true, false] as $booked) {
        foreach ([true, false] as $locked) {
            $seen[] = verdict($in_window, $booked, $locked)['reason'];
        }
    }
}

$seen = array_values(array_unique($seen));
sort($seen);
$expected = $known;
sort($expected);

check('no verdict the dropdown cannot describe', array_diff($seen, $known), []);
check('all eight combinations resolve', count($seen) > 0, true);

// The wording map in the AJAX handler must cover them.
$handler = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handler.php');
foreach ($known as $reason) {
    check(
        "the handler has wording for '{$reason}'",
        (bool) preg_match("/'" . preg_quote($reason, '/') . "'\s*=>/", $handler),
        true
    );
}

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
