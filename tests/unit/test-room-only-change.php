<?php
/**
 * Tests for the "internal room move" rule.
 *
 * An admin moving a booking from Room 2 to Room 3 must not send the customer
 * a "booking modified" mail. Editing anything else — with or without a room
 * change — must still notify. HRB_Booking_Manager::is_room_only_change() is
 * the switch the admin edit handler flips, so it is what these cases pin down.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-room-only-change.php
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
 */

define('ABSPATH', __DIR__);

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

/** A booking as it is currently stored. */
function stored(array $overrides = []): array {
    return array_merge([
        'room_id'          => 2,
        'status'           => 'confirmed',
        'payment_status'   => 'pending',
        'booking_date'     => '2026-09-10',
        'start_time'       => '11:00:00',
        'end_time'         => '14:00:00',
        'total_hours'      => '3.00',
        'extra_people'     => '0',
        'payment_method'   => 'onsite',
        'special_requests' => '',
        'admin_notes'      => null,
        'extras'           => [4, 7],
        'custom_price'     => '150.00',
        'first_name'       => 'Anna',
        'last_name'        => 'Schmidt',
        'email'            => 'anna@example.com',
        'phone'            => '0123456',
        'company'          => '',
    ], $overrides);
}

/** What the edit form posts back. */
function submitted(array $overrides = []): array {
    return array_merge([
        'room_id'          => 2,
        'status'           => 'confirmed',
        'payment_status'   => 'pending',
        'booking_date'     => '2026-09-10',
        'start_time'       => '11:00:00',
        'end_time'         => '14:00:00',
        'total_hours'      => 3,
        'extra_people'     => 0,
        'payment_method'   => 'onsite',
        'special_requests' => '',
        'admin_notes'      => '',
        'extras'           => [4, 7],
        'custom_price'     => '150.00',
        'first_name'       => 'Anna',
        'last_name'        => 'Schmidt',
        'email'            => 'anna@example.com',
        'phone'            => '0123456',
        'company'          => '',
    ], $overrides);
}

function room_only(array $sub_overrides): bool {
    return HRB_Booking_Manager::is_room_only_change(submitted($sub_overrides), stored());
}

function changed(array $sub_overrides): array {
    return HRB_Booking_Manager::diff_booking_fields(submitted($sub_overrides), stored());
}

// ---------------------------------------------------------------------------
// The reported case
// ---------------------------------------------------------------------------

echo "\n-- the internal room move --\n";

check('Room 2 -> Room 3 is a room-only change', room_only(['room_id' => 3]), true);
check('...and room_id is the only field flagged', changed(['room_id' => 3]), ['room_id']);
check('a room id posted as a string still matches', room_only(['room_id' => '3']), true);

// ---------------------------------------------------------------------------
// Saving with nothing edited
// ---------------------------------------------------------------------------

echo "\n-- nothing edited --\n";

check('an untouched save flags no fields', changed([]), []);
check('...and is not treated as a room-only move', room_only([]), false);

// Type and whitespace noise from the form must not read as an edit.
check('numeric string vs number', changed(['total_hours' => '3']), []);
check('float precision', changed(['total_hours' => 3.0]), []);
check('empty string vs stored NULL', changed(['admin_notes' => '']), []);
check('extras in a different order', changed(['extras' => [7, 4]]), []);
check('extras as strings', changed(['extras' => ['4', '7']]), []);

// ---------------------------------------------------------------------------
// Any other edit must still notify
// ---------------------------------------------------------------------------

echo "\n-- other edits still notify --\n";

check('date change', room_only(['booking_date' => '2026-09-11']), false);
check('start time change', room_only(['start_time' => '12:00:00']), false);
check('duration change', room_only(['total_hours' => 4]), false);
check('extra people change', room_only(['extra_people' => 2]), false);
check('status change', room_only(['status' => 'cancelled']), false);
check('payment status change', room_only(['payment_status' => 'paid']), false);
check('payment method change', room_only(['payment_method' => 'paypal']), false);
check('special requests change', room_only(['special_requests' => 'Late arrival']), false);
check('admin notes change', room_only(['admin_notes' => 'Moved by phone']), false);
check('extras added', room_only(['extras' => [4, 7, 9]]), false);
check('extras removed', room_only(['extras' => [4]]), false);
check('all extras removed', room_only(['extras' => []]), false);
check('manual price change', room_only(['custom_price' => '175.00']), false);
check('customer renamed', room_only(['first_name' => 'Anne']), false);
check('customer email changed', room_only(['email' => 'anne@example.com']), false);
check('customer phone changed', room_only(['phone' => '0999999']), false);
check('company filled in', room_only(['company' => 'ACME']), false);

// ---------------------------------------------------------------------------
// Room change combined with something else
// ---------------------------------------------------------------------------

echo "\n-- room change plus another edit --\n";

check('room + time is NOT room-only', room_only(['room_id' => 3, 'start_time' => '12:00:00']), false);
check('...and both fields are flagged', changed(['room_id' => 3, 'start_time' => '12:00:00']), ['room_id', 'start_time']);
check('room + extras is NOT room-only', room_only(['room_id' => 3, 'extras' => [4]]), false);
check('room + status is NOT room-only', room_only(['room_id' => 3, 'status' => 'completed']), false);
check('room + customer rename is NOT room-only', room_only(['room_id' => 3, 'last_name' => 'Meyer']), false);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
