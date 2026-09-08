<?php
/**
 * Tests for who owes the cancellation fee, and which mail they get.
 *
 * The rule used to turn on the payment *method*: only cash and on-site
 * bookings were charged, so a customer who chose PayPal and then never paid
 * cancelled for free. What actually matters is whether the money arrived. A
 * settled booking is not charged - the amount already taken is kept instead of
 * being refunded, which is the penalty - and everything still outstanding owes
 * the flat fee.
 *
 * The two outcomes are different letters, not one letter with a block that is
 * sometimes empty, so the template each one renders from is pinned here too.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-cancellation-fee.php
 *
 * @package HourlyRoomBooking
 * @since 1.10.0
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

require_once HRB_PLUGIN_DIR . 'includes/class-booking-manager.php';
require_once HRB_PLUGIN_DIR . 'includes/class-notification-manager.php';

// ---------------------------------------------------------------------------
// Who owes the fee
// ---------------------------------------------------------------------------

echo "\n-- a booking that was paid for owes nothing --\n";

check('paid', HRB_Booking_Manager::charges_cancellation_fee('paid'), false);
check('completed', HRB_Booking_Manager::charges_cancellation_fee('completed'), false);
check('case does not matter', HRB_Booking_Manager::charges_cancellation_fee('Paid'), false);
check('nor does whitespace', HRB_Booking_Manager::charges_cancellation_fee(' completed '), false);

echo "\n-- anything still outstanding owes the fee --\n";

check('pending', HRB_Booking_Manager::charges_cancellation_fee('pending'), true);
check('failed', HRB_Booking_Manager::charges_cancellation_fee('failed'), true);
check('an empty status', HRB_Booking_Manager::charges_cancellation_fee(''), true);

// This is the case the old method-based rule let through: the customer picked
// PayPal, never completed the payment, and cancelled for free.
check('a PayPal booking that was never paid', HRB_Booking_Manager::charges_cancellation_fee('pending'), true);

// ---------------------------------------------------------------------------
// Which letter they get
// ---------------------------------------------------------------------------

echo "\n-- the template each outcome renders from --\n";

$owing  = (object) ['cancellation_fee' => 15.00];
$settled = (object) ['cancellation_fee' => 0.00];
$missing = (object) [];

check(
    'a cancellation with a fee gets the fee template',
    HRB_Notification_Manager::template_slug($owing, 'booking_cancelled'),
    'booking_cancelled_fee'
);

check(
    'a cancellation without one gets the plain template',
    HRB_Notification_Manager::template_slug($settled, 'booking_cancelled'),
    'booking_cancelled'
);

check(
    'a booking with no fee column at all gets the plain template',
    HRB_Notification_Manager::template_slug($missing, 'booking_cancelled'),
    'booking_cancelled'
);

// The fee only ever splits the cancellation mail; nothing else is rerouted.
check(
    'a confirmation is never rerouted',
    HRB_Notification_Manager::template_slug($owing, 'booking_confirmation'),
    'booking_confirmation'
);

check(
    'nor is a reminder',
    HRB_Notification_Manager::template_slug($owing, 'booking_reminder'),
    'booking_reminder'
);

// ---------------------------------------------------------------------------
// The two templates are bundled and say the right things
// ---------------------------------------------------------------------------

echo "\n-- the bundled templates --\n";

$templates = include HRB_PLUGIN_DIR . 'includes/email-templates-data.php';

$by_key = [];
foreach ($templates as $t) {
    $by_key[$t['template_key']] = $t;
}

check('the fee template is bundled', isset($by_key['booking_cancelled_fee_user']), true);
check('the plain one still is too', isset($by_key['booking_cancelled_user']), true);

$fee = $by_key['booking_cancelled_fee_user']['html_content'] ?? '';

// A fee demand without an account to pay it into is worthless, and the bank
// details reach the template through these tokens.
foreach (['{cancellation_fee}', '{bank_holder}', '{bank_iban}', '{bank_bic}', '{booking_reference}'] as $token) {
    check("the fee template carries {$token}", strpos($fee, $token) !== false, true);
}

check('it refuses PayPal in so many words', strpos($fee, 'PayPal') !== false, true);
check('and says the invoice is attached', strpos($fee, 'PDF') !== false, true);

// The plain cancellation must not carry any of it any more, or a customer who
// has already paid would be shown a fee block.
$plain = $by_key['booking_cancelled_user']['html_content'] ?? '';

check('the plain template mentions no fee', strpos($plain, 'cancellation_fee') !== false, false);
check('and no bank details', strpos($plain, 'bank_iban') !== false, false);

// Adding a template to the bundle only reaches existing sites when the bundle
// version changes; rewriting one needs the per-template version bumped.
$database = file_get_contents(HRB_PLUGIN_DIR . 'includes/class-database.php');

check(
    'the bundle version was bumped so the new template is seeded',
    (bool) preg_match("/bundle_version = '2026-09-08-cancellation-fee-template'/", $database),
    true
);

check(
    'and the plain template is on the resync list so its fee block goes away',
    (bool) preg_match("/template_keys\s*=\s*array\([^)]*'booking_cancelled_user'/", $database),
    true
);

// ---------------------------------------------------------------------------
// The bank details must survive a site that never opened the settings screen
// ---------------------------------------------------------------------------

echo "\n-- where the bank details come from --\n";

$invoice = file_get_contents(HRB_PLUGIN_DIR . 'includes/class-invoice-generator.php');
$at      = strpos($invoice, 'function get_bank_details');
$body    = false === $at ? '' : substr($invoice, $at, 900);

check('get_bank_details() was found', $body !== '', true);

// get_option($key, '') returns the empty fallback on a site whose options row
// was never written, which put a blank IBAN in front of customers.
check(
    'it does not read the options directly',
    (bool) preg_match("/get_option\(\s*'hrb_bank_/", $body),
    false
);

check(
    'it reads through HRB_Settings, which knows the defaults',
    (bool) preg_match('/settings->get\(\s*[\'"]hrb_bank_/', $body),
    true
);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
