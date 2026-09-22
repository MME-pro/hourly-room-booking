<?php
/**
 * Tests for slots that run past midnight meeting a room or master lock.
 *
 * Reported from the live site: Cozy Garden Retreat was locked from
 * 2026-09-24 17:13 to 2026-09-25 18:13, and the public room page still
 * offered 22:00-00:00, 22:30-00:30, 23:00-01:00 and 23:30-01:30 on the 24th.
 *
 * The slot picker pinned both ends of a slot to the booking date, so a slot
 * finishing after midnight came out as "24th 22:00 to 24th 00:00" — a window
 * running backwards, which fails every overlap test it is put through. The
 * afternoon slots were correctly blocked, which is what made it look like the
 * lock was working.
 *
 * The second half of the same bug: locks were only fetched for the booking
 * date itself, so a lock sitting entirely on the following day never even
 * reached the comparison.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-slot-locks.php
 *
 * @package HourlyRoomBooking
 * @since 1.18.1
 */

define('ABSPATH', __DIR__);

require_once dirname(__DIR__, 2) . '/includes/class-ajax-handler.php';

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

function range_of(string $start, string $end): array {
    return HRB_Ajax_Handler::slot_datetime_range('2026-09-24', $start, $end);
}

/** The lock from the report. */
function locked(string $start, string $end): bool {
    return HRB_Ajax_Handler::slot_overlaps_lock(
        '2026-09-24',
        $start,
        $end,
        '2026-09-24 17:13:00',
        '2026-09-25 18:13:00'
    );
}

// ---------------------------------------------------------------------------
// Where a slot actually sits on the clock
// ---------------------------------------------------------------------------

echo "\n-- slot_datetime_range --\n";

check(
    'a slot inside one day stays on that day',
    range_of('10:00:00', '12:00:00'),
    ['2026-09-24 10:00:00', '2026-09-24 12:00:00']
);
check(
    'a slot ending at midnight ends on the next day',
    range_of('22:00:00', '00:00:00'),
    ['2026-09-24 22:00:00', '2026-09-25 00:00:00']
);
check(
    'a slot ending after midnight ends on the next day',
    range_of('23:30:00', '01:30:00'),
    ['2026-09-24 23:30:00', '2026-09-25 01:30:00']
);
check(
    'a full 24-hour slot ends at the same time the next day',
    range_of('08:00:00', '08:00:00'),
    ['2026-09-24 08:00:00', '2026-09-25 08:00:00']
);

// ---------------------------------------------------------------------------
// The slots from the report
// ---------------------------------------------------------------------------

echo "\n-- the reported lock: 24th 17:13 to 25th 18:13 --\n";

// These four were offered as available on the live site and are the bug.
check('22:00 - 00:00 is locked', locked('22:00:00', '00:00:00'), true);
check('22:30 - 00:30 is locked', locked('22:30:00', '00:30:00'), true);
check('23:00 - 01:00 is locked', locked('23:00:00', '01:00:00'), true);
check('23:30 - 01:30 is locked', locked('23:30:00', '01:30:00'), true);

// These were already correct and must stay that way.
check('15:30 - 17:30 is locked, it runs into 17:13', locked('15:30:00', '17:30:00'), true);
check('16:00 - 18:00 is locked', locked('16:00:00', '18:00:00'), true);
check('21:00 - 23:00 is locked', locked('21:00:00', '23:00:00'), true);

check('15:00 - 17:00 is free, it finishes before 17:13', locked('15:00:00', '17:00:00'), false);
check('08:00 - 10:00 is free', locked('08:00:00', '10:00:00'), false);
check('14:00 - 16:00 is free', locked('14:00:00', '16:00:00'), false);

// ---------------------------------------------------------------------------
// Boundaries
// ---------------------------------------------------------------------------

echo "\n-- touching, but not overlapping --\n";

check(
    'a slot ending exactly when the lock starts is free',
    HRB_Ajax_Handler::slot_overlaps_lock('2026-09-24', '15:00:00', '17:00:00', '2026-09-24 17:00:00', '2026-09-25 18:00:00'),
    false
);
check(
    'a slot starting exactly when the lock ends is free',
    HRB_Ajax_Handler::slot_overlaps_lock('2026-09-24', '18:00:00', '20:00:00', '2026-09-24 10:00:00', '2026-09-24 18:00:00'),
    false
);
check(
    'a slot one minute into the lock is caught',
    HRB_Ajax_Handler::slot_overlaps_lock('2026-09-24', '15:00:00', '17:01:00', '2026-09-24 17:00:00', '2026-09-25 18:00:00'),
    true
);

// ---------------------------------------------------------------------------
// A lock that lives entirely on the following day
// ---------------------------------------------------------------------------

echo "\n-- a lock on the next day only --\n";

// The other half of the bug: this lock is nowhere near the 24th, but a slot
// starting on the 24th can still run into it.
$next_day_lock = ['2026-09-25 00:30:00', '2026-09-25 05:00:00'];

check(
    'a slot crossing midnight into it is locked',
    HRB_Ajax_Handler::slot_overlaps_lock('2026-09-24', '23:30:00', '01:30:00', $next_day_lock[0], $next_day_lock[1]),
    true
);
check(
    'a slot that stops at midnight is free',
    HRB_Ajax_Handler::slot_overlaps_lock('2026-09-24', '22:00:00', '00:00:00', $next_day_lock[0], $next_day_lock[1]),
    false
);
check(
    'a daytime slot on the 24th is untouched by it',
    HRB_Ajax_Handler::slot_overlaps_lock('2026-09-24', '10:00:00', '12:00:00', $next_day_lock[0], $next_day_lock[1]),
    false
);

// The fetch window has to reach that lock in the first place. It is built as
// the booking date through two days on, so a lock anywhere in the following
// day is loaded.
echo "\n-- the fetch window --\n";

$window_start = '2026-09-24' . ' 00:00:00';
$window_end   = date('Y-m-d H:i:s', strtotime('2026-09-24 +2 days'));

check('the window opens at the start of the booking date', $window_start, '2026-09-24 00:00:00');
check('and runs two days on', $window_end, '2026-09-26 00:00:00');
check(
    'a next-day lock falls inside it',
    $next_day_lock[0] < $window_end && $next_day_lock[1] > $window_start,
    true
);
check(
    'the longest possible slot still ends inside it',
    range_of('23:30:00', '23:30:00')[1] < $window_end,
    true
);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
