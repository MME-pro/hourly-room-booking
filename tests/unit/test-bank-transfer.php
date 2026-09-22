<?php
/**
 * Tests for bank transfer: the admin-only payment method and the internal
 * "paid by bank transfer" marker that sits beside it.
 *
 * Both halves are desk work — an admin picks the method, and the desk notes
 * when the money actually landed — so the things worth pinning are the ones
 * that would quietly let either reach a customer:
 *
 *  - The method is offered to an admin and to nobody else. The public form
 *    never renders it, but the form is not the only way in: the AJAX endpoint
 *    hands raw $_POST to HRB_Input_Validator, so the validator is the boundary
 *    that actually has to refuse it. It is unlocked by an explicit argument,
 *    never by anything carried in the submitted data.
 *
 *  - Ticking it sends no email. The marker is kept out of the edit form's
 *    change comparison on purpose, so an edit that touches only the marker
 *    leaves no diff — and an empty diff has to count as "nothing the customer
 *    would notice", not as "notify them".
 *
 *  - The account shown next to the checkbox falls back to the company bank
 *    details the cancellation-fee invoice already uses, so the IBAN is not
 *    typed twice.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-bank-transfer.php
 *
 * @package HourlyRoomBooking
 * @since 1.18.0
 */

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

define('ABSPATH', __DIR__);

$GLOBALS['wp_options'] = [];

function __($text, $domain = null) { return $text; }
function apply_filters($tag, $value) { return $value; }
function add_action() { return true; }
function add_filter() { return true; }
function get_option($key, $default = false) { return $GLOBALS['wp_options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['wp_options'][$key] = $value; return true; }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($email) { return trim((string) $email); }
function is_email($email) { return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL); }

class WP_Error {
    private array $messages = [];

    public function add($code, $message) { $this->messages[$code] = $message; }
    public function get_error_messages() { return array_values($this->messages); }
    public function get_error_codes() { return array_keys($this->messages); }
}

require_once dirname(__DIR__, 2) . '/includes/class-settings.php';
require_once dirname(__DIR__, 2) . '/includes/currency-helpers.php';
require_once dirname(__DIR__, 2) . '/includes/class-input-validator.php';
require_once dirname(__DIR__, 2) . '/includes/class-booking-manager.php';

// ---------------------------------------------------------------------------
// Harness
// ---------------------------------------------------------------------------

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

/**
 * Seed the HRB_Settings singleton's cache.
 *
 * get() returns early on a cache hit, so this drives the real helpers without
 * a database. The helpers reach for the singleton themselves, which is why the
 * singleton — rather than a throwaway instance — is what gets seeded.
 */
$settings_reflection = new ReflectionClass('HRB_Settings');
$instance_prop = $settings_reflection->getProperty('instance');
$instance_prop->setAccessible(true);
$cache_prop = $settings_reflection->getProperty('settings_cache');
$cache_prop->setAccessible(true);

function seed_settings(array $cache): void {
    global $settings_reflection, $instance_prop, $cache_prop;

    $settings = $settings_reflection->newInstanceWithoutConstructor();
    $cache_prop->setValue($settings, $cache);
    $instance_prop->setValue(null, $settings);
}

// ---------------------------------------------------------------------------
// It is a note, not a payment method
// ---------------------------------------------------------------------------

echo "\n-- the public booking flow still refuses it as a method --\n";

function rejects(string $payment_method, bool $allow_backend_methods = false): bool {
    $result = HRB_Input_Validator::getInstance()->validate_booking_data([
        'room_id'        => 3,
        'booking_date'   => '2026-10-01',
        'start_time'     => '10:00',
        'end_time'       => '12:00',
        'extra_people'   => 0,
        'payment_method' => $payment_method,
    ], $allow_backend_methods);

    return $result instanceof WP_Error;
}

check('a customer cannot submit bank transfer', rejects('bank_transfer'), true);
check('an admin can', rejects('bank_transfer', true), false);
check('on-site is open to both', rejects('onsite'), false);
check('PayPal is open to both', rejects('paypal'), false);
check('nonsense is refused from the public flow', rejects('bitcoin'), true);
check('nonsense is refused from the admin too', rejects('bitcoin', true), true);

// The gate is an argument, not data: nothing a customer can put in the POST
// body may unlock the method.
$smuggled = [
    'room_id'               => 3,
    'booking_date'          => '2026-10-01',
    'start_time'            => '10:00',
    'end_time'              => '12:00',
    'extra_people'          => 0,
    'payment_method'        => 'bank_transfer',
    'allow_backend_methods' => true,
    'is_admin'              => 1,
    'created_by_admin'      => 1,
];
check(
    'a forged admin flag in the submitted data changes nothing',
    HRB_Input_Validator::getInstance()->validate_booking_data($smuggled) instanceof WP_Error,
    true
);

echo "\n-- which methods each form may offer --\n";

seed_settings(['hrb_bank_transfer_enabled' => 1]);

check(
    'the public list is on-site and PayPal, nothing else',
    array_keys(hrb_get_selectable_payment_methods()),
    ['onsite', 'paypal']
);
check(
    'the admin list adds bank transfer',
    array_keys(hrb_get_selectable_payment_methods(true)),
    ['onsite', 'paypal', 'bank_transfer']
);

seed_settings(['hrb_bank_transfer_enabled' => 0]);
check(
    'switching it off removes it from the admin list too',
    array_keys(hrb_get_selectable_payment_methods(true)),
    ['onsite', 'paypal']
);
check(
    'but it is still classed as admin-only, so an existing booking keeps it',
    hrb_is_backend_only_payment_method('bank_transfer'),
    true
);
check('case and padding do not matter', hrb_is_backend_only_payment_method(' BANK_TRANSFER '), true);
check('on-site is not admin-only', hrb_is_backend_only_payment_method('onsite'), false);
check('paypal is not admin-only', hrb_is_backend_only_payment_method('paypal'), false);

echo "\n-- hrb_bank_transfer_enabled --\n";

seed_settings(['hrb_bank_transfer_enabled' => 1]);
check('on when the setting is on', hrb_bank_transfer_enabled(), true);

seed_settings(['hrb_bank_transfer_enabled' => 0]);
check('off when the setting is off', hrb_bank_transfer_enabled(), false);

seed_settings(['hrb_bank_transfer_enabled' => '1']);
check('a stored string still reads as on', hrb_bank_transfer_enabled(), true);

// ---------------------------------------------------------------------------
// Ticking it must not mail the customer
// ---------------------------------------------------------------------------

echo "\n-- is_internal_only_change --\n";


$unchanged = [
    'room_id'        => 4,
    'status'         => 'confirmed',
    'payment_status' => 'pending',
    'booking_date'   => '2026-10-01',
    'admin_notes'    => 'walk-in',
];

check(
    'an edit that only ticked the marker leaves no diff at all',
    HRB_Booking_Manager::diff_booking_fields($unchanged, $unchanged),
    []
);
check(
    'and therefore counts as internal, so no email goes out',
    HRB_Booking_Manager::is_internal_only_change($unchanged, $unchanged),
    true
);

$room_moved = array_merge($unchanged, ['room_id' => 7]);
check('an internal room move is still internal', HRB_Booking_Manager::is_internal_only_change($room_moved, $unchanged), true);
check('and is still reported as a room-only change', HRB_Booking_Manager::is_room_only_change($room_moved, $unchanged), true);

$date_changed = array_merge($unchanged, ['booking_date' => '2026-10-02']);
check('a real change is not internal', HRB_Booking_Manager::is_internal_only_change($date_changed, $unchanged), false);

$date_and_room = array_merge($unchanged, ['room_id' => 7, 'booking_date' => '2026-10-02']);
check('a room move plus a real change is not internal', HRB_Booking_Manager::is_internal_only_change($date_and_room, $unchanged), false);

// The distinction that matters: a no-op save used to be "notify", because
// is_room_only_change() only ever matched exactly ['room_id'].
check('a no-op save is not a room-only change', HRB_Booking_Manager::is_room_only_change($unchanged, $unchanged), false);
check('but it is an internal one', HRB_Booking_Manager::is_internal_only_change($unchanged, $unchanged), true);

// ---------------------------------------------------------------------------
// The account shown next to the checkbox
// ---------------------------------------------------------------------------

echo "\n-- hrb_get_bank_transfer_details --\n";

seed_settings([
    'hrb_bank_transfer_bank_name'      => 'Postbank',
    'hrb_bank_transfer_account_holder' => 'Room Booking GmbH',
    'hrb_bank_transfer_iban'           => 'DE02120300000000202051',
    'hrb_bank_transfer_bic'            => 'BYLADEM1001',
    'hrb_bank_transfer_reference'      => 'Booking {booking_reference}',
    'hrb_bank_transfer_instructions'   => 'Check the statement weekly.',
    'hrb_bank_account_holder'          => 'Company Fallback',
    'hrb_bank_iban'                    => 'DE37590100660861429667',
    'hrb_bank_bic'                     => 'PBNKDEFF',
]);

$details = hrb_get_bank_transfer_details();
check('its own bank name is used', $details['bank_name'], 'Postbank');
check('its own account holder wins over the company one', $details['account_holder'], 'Room Booking GmbH');
check('its own IBAN wins over the company one', $details['iban'], 'DE02120300000000202051');
check('its own BIC wins over the company one', $details['bic'], 'BYLADEM1001');
check('the instructions come through', $details['instructions'], 'Check the statement weekly.');

seed_settings([
    'hrb_bank_transfer_bank_name'      => '',
    'hrb_bank_transfer_account_holder' => '',
    'hrb_bank_transfer_iban'           => '   ',
    'hrb_bank_transfer_bic'            => '',
    'hrb_bank_transfer_reference'      => 'Booking {booking_reference}',
    'hrb_bank_transfer_instructions'   => '',
    'hrb_bank_account_holder'          => 'Company Fallback',
    'hrb_bank_iban'                    => 'DE37590100660861429667',
    'hrb_bank_bic'                     => 'PBNKDEFF',
]);

$fallback = hrb_get_bank_transfer_details();
check('an empty account holder falls back to Company Information', $fallback['account_holder'], 'Company Fallback');
check('a whitespace-only IBAN counts as empty and falls back', $fallback['iban'], 'DE37590100660861429667');
check('an empty BIC falls back', $fallback['bic'], 'PBNKDEFF');
check('the bank name has no fallback and stays empty', $fallback['bank_name'], '');

echo "\n-- hrb_get_bank_transfer_reference --\n";

check(
    'an existing booking gets its reference substituted',
    hrb_get_bank_transfer_reference('HRB-2026-0042'),
    'Booking HRB-2026-0042'
);
check(
    'the add form has no reference yet, so the placeholder stands',
    hrb_get_bank_transfer_reference(),
    'Booking {booking_reference}'
);

seed_settings(['hrb_bank_transfer_reference' => '']);
check('an unset reference stays empty rather than printing a placeholder', hrb_get_bank_transfer_reference('HRB-1'), '');

// ---------------------------------------------------------------------------
// Settings schema
// ---------------------------------------------------------------------------

echo "\n-- settings schema --\n";

$settings = $settings_reflection->newInstanceWithoutConstructor();
$defaults_prop = $settings_reflection->getProperty('default_settings');
$defaults_prop->setAccessible(true);
$schema = $defaults_prop->getValue($settings);

foreach ([
    'hrb_bank_transfer_enabled',
    'hrb_bank_transfer_bank_name',
    'hrb_bank_transfer_account_holder',
    'hrb_bank_transfer_iban',
    'hrb_bank_transfer_bic',
    'hrb_bank_transfer_reference',
    'hrb_bank_transfer_instructions',
] as $key) {
    check("{$key} is registered", isset($schema[$key]), true);
    check("{$key} has a sanitize callback that exists", function_exists($schema[$key]['sanitize'] ?? ''), true);
}

check('the account details default to empty so the fallback applies', $schema['hrb_bank_transfer_iban']['default'], '');
check('the reference ships with a usable template', $schema['hrb_bank_transfer_reference']['default'], 'Booking {booking_reference}');

$groups = $settings->get_settings_groups();
check('there is a Bank Transfer tab', isset($groups['bank_transfer']), true);
check(
    'the tab carries every bank transfer setting',
    $groups['bank_transfer']['settings'],
    [
        'hrb_bank_transfer_enabled',
        'hrb_bank_transfer_bank_name',
        'hrb_bank_transfer_account_holder',
        'hrb_bank_transfer_iban',
        'hrb_bank_transfer_bic',
        'hrb_bank_transfer_reference',
        'hrb_bank_transfer_instructions',
    ]
);
check(
    'the company bank details stay where the invoice reads them from',
    in_array('hrb_bank_iban', $groups['company']['settings'], true),
    true
);

// ---------------------------------------------------------------------------
// The column the marker lives in
// ---------------------------------------------------------------------------

echo "\n-- schema and migration --\n";

$database_src = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
$bootstrap_src = file_get_contents(dirname(__DIR__, 2) . '/hourly-room-booking.php');

check(
    'a fresh install creates the column',
    (bool) preg_match('/paid_by_bank_transfer tinyint\(1\) NOT NULL DEFAULT 0,/', $database_src),
    true
);
check(
    'an existing install gets a migration',
    (bool) preg_match('/function ensure_paid_by_bank_transfer_column/', $database_src),
    true
);
check(
    'the migration is option-gated so it runs once',
    (bool) preg_match("/hrb_paid_by_bank_transfer_migrated/", $database_src),
    true
);
check(
    'the migration is actually hooked up',
    (bool) preg_match("/ensure_paid_by_bank_transfer_column/", $bootstrap_src),
    true
);

$manager_src = file_get_contents(dirname(__DIR__, 2) . '/includes/class-booking-manager.php');
check(
    'create_booking writes the column',
    (bool) preg_match("/'paid_by_bank_transfer' =>/", $manager_src),
    true
);

// wpdb::insert pairs $data with $format positionally, so a mismatch silently
// writes the wrong types rather than failing loudly.
preg_match('/\$booking_data = array\((.*?)\n        \);/s', $manager_src, $data_match);
preg_match("/array\('%s', '%d', '%d', '%s'.*?\)\n            \);/s", $manager_src, $format_match);
$data_keys = preg_match_all("/^\s*'[a-z_]+' =>/m", $data_match[1] ?? '');
$format_specs = preg_match_all("/'%[sdf]'/", $format_match[0] ?? '');
// Guard the guard: if either pattern stops matching, the comparison below
// would pass on 0 === 0 and quietly stop checking anything.
check('the insert data array was located', $data_keys > 20, true);
check('the insert format array was located', $format_specs > 20, true);
check('the insert format array still matches the column count', $format_specs, $data_keys);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
