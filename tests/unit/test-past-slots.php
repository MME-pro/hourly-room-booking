<?php
/**
 * Tests for admin booking of slots that have already started.
 *
 * Covers HRB_Ajax_Handler::is_slot_in_past() and ::is_slot_blocked_as_past(),
 * the two rules that decide whether the time-slot picker offers a slot the
 * clock has already passed. Public visitors must never get one; the admin
 * always does (walk-in arriving late, or entering a booking afterwards).
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-past-slots.php
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
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

// "Now" for every case below: 2026-09-03 at 11:05.
const NOW_DATE = '2026-09-03';
const NOW_TIME = '11:05';

function in_past(string $date, string $time): bool {
    return HRB_Ajax_Handler::is_slot_in_past($date, $time, NOW_DATE, NOW_TIME);
}

function blocked(string $date, string $time, bool $is_admin): bool {
    return HRB_Ajax_Handler::is_slot_blocked_as_past($date, $time, NOW_DATE, NOW_TIME, $is_admin);
}

// ---------------------------------------------------------------------------
// Which slots count as past
// ---------------------------------------------------------------------------

echo "\n-- is_slot_in_past --\n";

check('the 11:00 slot has started by 11:05', in_past('2026-09-03', '11:00'), true);
check('the 11:30 slot has not started yet', in_past('2026-09-03', '11:30'), false);
check('a slot starting exactly now is not past', in_past('2026-09-03', '11:05'), false);
check('an earlier slot today is past', in_past('2026-09-03', '08:00'), true);
check('a later slot today is not past', in_past('2026-09-03', '23:30'), false);
check('every slot on an earlier date is past', in_past('2026-09-02', '23:30'), true);
check('the very first slot of an earlier date is past', in_past('2026-09-02', '00:00'), true);
check('no slot on a later date is past', in_past('2026-09-04', '00:00'), false);
check('month rollover compares correctly', in_past('2026-08-31', '23:30'), true);
check('year rollover compares correctly', in_past('2027-01-01', '00:00'), false);

// ---------------------------------------------------------------------------
// Who is allowed to book them
// ---------------------------------------------------------------------------

echo "\n-- is_slot_blocked_as_past (public) --\n";

check('public cannot take the 11:00 slot at 11:05', blocked('2026-09-03', '11:00', false), true);
check('public can still take 11:30', blocked('2026-09-03', '11:30', false), false);
check('public cannot take yesterday', blocked('2026-09-02', '14:00', false), true);
check('public can take tomorrow', blocked('2026-09-04', '09:00', false), false);

echo "\n-- is_slot_blocked_as_past (admin) --\n";

// The reported case: customer walks in at 11:05 for a booking that starts at
// 11:00. The admin must be able to pick that slot.
check('admin CAN take the 11:00 slot at 11:05', blocked('2026-09-03', '11:00', true), false);
check('admin CAN take an earlier slot today', blocked('2026-09-03', '08:00', true), false);
check('admin CAN take a slot on a past date', blocked('2026-09-02', '14:00', true), false);
check('admin is unaffected for future slots', blocked('2026-09-04', '09:00', true), false);

// ---------------------------------------------------------------------------
// The flag the picker uses for its "Past — bookable" badge stays accurate
// even though the slot is no longer blocked.
// ---------------------------------------------------------------------------

echo "\n-- badge vs blocking --\n";

check('a past slot is still reported as past for the admin', in_past('2026-09-03', '11:00'), true);
check('...while no longer being blocked', blocked('2026-09-03', '11:00', true), false);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
