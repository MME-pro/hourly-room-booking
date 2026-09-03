<?php
/**
 * Regression tests for the doubled PayPal total.
 *
 * Reported as €87.55 showing up as €175.10 on cancellation and on edit, PayPal
 * only (booking HRB-531823-3221). Cause: the booking total was rebuilt as
 * SUM(completed) + SUM(pending) over the payment rows, so a stale pending row
 * for an already-captured charge got counted a second time. A PayPal capture
 * that missed its pending row and inserted a fresh completed one left exactly
 * that pair behind.
 *
 * These cases were first reproduced against the real schema, then pinned here.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-booking-total.php
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
 */

define('ABSPATH', __DIR__);

require_once dirname(__DIR__, 2) . '/includes/class-payment-manager.php';

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

/** Build a payment row. */
function row(string $status, float $amount, float $fees, $transaction_id = null): object {
    return (object) [
        'id'             => ++$GLOBALS['row_id'],
        'transaction_id' => $transaction_id,
        'amount'         => $amount,
        'fees'           => $fees,
        'status'         => $status,
    ];
}

$GLOBALS['row_id'] = 0;

function total(array $rows): float {
    return HRB_Payment_Manager::total_from_payment_rows($rows)['total'];
}

function fees(array $rows): float {
    return HRB_Payment_Manager::total_from_payment_rows($rows)['fees'];
}

// ---------------------------------------------------------------------------
// The reported bug
// ---------------------------------------------------------------------------

echo "\n-- the reported bug --\n";

// €85.00 room + €2.55 PayPal fee, captured. The capture inserted a completed
// row without retiring the pending one it was created from.
check(
    'a captured charge with its stale pending row is counted once',
    total([
        row('pending',   87.55, 2.55),
        row('completed', 87.55, 2.55, 'CAPTURE1'),
    ]),
    87.55
);

check(
    'the fee is not doubled either',
    fees([
        row('pending',   87.55, 2.55),
        row('completed', 87.55, 2.55, 'CAPTURE1'),
    ]),
    2.55
);

check(
    'two abandoned checkout attempts plus the capture',
    total([
        row('pending',   87.55, 2.55),
        row('pending',   87.55, 2.55),
        row('completed', 87.55, 2.55, 'CAPTURE1'),
    ]),
    87.55
);

// ---------------------------------------------------------------------------
// The cases that already worked must keep working
// ---------------------------------------------------------------------------

echo "\n-- unchanged behaviour --\n";

check('a clean captured charge', total([row('completed', 87.55, 2.55, 'CAPTURE1')]), 87.55);
check('an unpaid booking counts its pending charge', total([row('pending', 87.55, 2.55)]), 87.55);
check('a booking with no payment rows', total([]), 0.0);

check(
    'an on-site booking awaiting collection',
    total([row('pending', 85.00, 0.00)]),
    85.00
);

// ---------------------------------------------------------------------------
// Additional charges still add up
// ---------------------------------------------------------------------------

echo "\n-- additional charges --\n";

check(
    'a pending extra is added to the paid charge',
    total([
        row('completed', 87.55, 2.55, 'CAPTURE1'),
        row('pending',   30.90, 0.90, 'ADD_1_5'),
    ]),
    118.45
);

check(
    'a collected extra is added too',
    total([
        row('completed', 87.55, 2.55, 'CAPTURE1'),
        row('completed', 30.90, 0.90, 'ADD_1_5'),
    ]),
    118.45
);

check(
    'several extras all count',
    total([
        row('completed', 87.55, 2.55, 'CAPTURE1'),
        row('completed', 30.90, 0.90, 'ADD_1_5'),
        row('pending',   15.45, 0.45, 'ADD_2_5'),
    ]),
    133.90
);

// The stale row must not come back through the extras path.
check(
    'a stale pending charge next to a legitimate extra',
    total([
        row('pending',   87.55, 2.55),
        row('completed', 87.55, 2.55, 'CAPTURE1'),
        row('pending',   30.90, 0.90, 'ADD_1_5'),
    ]),
    118.45
);

// ---------------------------------------------------------------------------
// Rows that must never count
// ---------------------------------------------------------------------------

echo "\n-- excluded rows --\n";

check(
    'a cancellation fee is not part of the booking total',
    total([
        row('completed', 87.55, 2.55, 'CAPTURE1'),
        row('pending',   15.00, 0.00, 'CANCELFEE_5'),
    ]),
    87.55
);

check(
    'a collected cancellation fee is not part of it either',
    total([
        row('completed', 87.55, 2.55, 'CAPTURE1'),
        row('completed', 15.00, 0.00, 'CANCELFEE_5'),
    ]),
    87.55
);

check(
    'cancelled rows do not count',
    total([
        row('cancelled', 87.55, 2.55),
        row('completed', 87.55, 2.55, 'CAPTURE1'),
    ]),
    87.55
);

check(
    'failed rows do not count',
    total([
        row('failed',    87.55, 2.55),
        row('completed', 87.55, 2.55, 'CAPTURE1'),
    ]),
    87.55
);

check(
    'a fully cancelled booking is worth nothing',
    total([row('cancelled', 87.55, 2.55)]),
    0.0
);

// ---------------------------------------------------------------------------
// Shape tolerance — rows arrive from $wpdb as objects of strings
// ---------------------------------------------------------------------------

echo "\n-- input shapes --\n";

check(
    'string amounts from the database',
    total([
        (object) ['transaction_id' => null, 'amount' => '87.55', 'fees' => '2.55', 'status' => 'pending'],
        (object) ['transaction_id' => 'CAPTURE1', 'amount' => '87.55', 'fees' => '2.55', 'status' => 'completed'],
    ]),
    87.55
);

check(
    'array rows',
    total([
        ['transaction_id' => null, 'amount' => 87.55, 'fees' => 2.55, 'status' => 'pending'],
        ['transaction_id' => 'CAPTURE1', 'amount' => 87.55, 'fees' => 2.55, 'status' => 'completed'],
    ]),
    87.55
);

check(
    'the "paid" status is treated as collected',
    total([
        row('pending', 87.55, 2.55),
        row('paid',    87.55, 2.55, 'CAPTURE1'),
    ]),
    87.55
);

check(
    'status casing from the database',
    total([
        (object) ['transaction_id' => null, 'amount' => 87.55, 'fees' => 2.55, 'status' => 'Pending'],
        (object) ['transaction_id' => 'CAPTURE1', 'amount' => 87.55, 'fees' => 2.55, 'status' => 'Completed'],
    ]),
    87.55
);

// ---------------------------------------------------------------------------
// Month-end correction: the IDs the payments list table posts for bulk delete
// ---------------------------------------------------------------------------

echo "\n-- parse_payment_id_list --\n";

function payment_ids($raw): array {
    return HRB_Payment_Manager::parse_payment_id_list($raw);
}

check('a plain list', payment_ids('12,13,14'), [12, 13, 14]);
check('a single id', payment_ids('7'), [7]);
check('nothing selected', payment_ids(''), []);
check('whitespace around ids', payment_ids(' 12 , 13 '), [12, 13]);
check('empty entries between ids', payment_ids('12,,13'), [12, 13]);
check('non-numeric entries are dropped', payment_ids('12,abc,13'), [12, 13]);
check('a zero id is dropped', payment_ids('0,12'), [12]);
check('a negative id is dropped', payment_ids('-5,12'), [12]);
check('duplicates collapse', payment_ids('12,12,13'), [12, 13]);
check('array input', payment_ids(['12', '', 'abc', '13']), [12, 13]);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
