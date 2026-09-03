<?php
/**
 * Tests for the multi-recipient team notification list.
 *
 * Covers the hrb_*_email_list helpers, the email_list setting type, and
 * HRB_Settings::get_notification_recipients() — the single place that decides
 * who receives a booking notification.
 *
 * The plugin has no PHPUnit dependency, so this runs standalone against just
 * enough of WordPress to load includes/class-settings.php:
 *
 *     php tests/unit/test-settings-email-list.php
 *
 * Exits non-zero on the first failing expectation set, so it can be dropped
 * into CI as-is.
 *
 * @package HourlyRoomBooking
 * @since 1.6.0
 */

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

define('ABSPATH', __DIR__);

$GLOBALS['wp_options'] = ['admin_email' => 'wpadmin@example.com'];

function sanitize_email($email) {
    $email = trim((string) $email);

    return preg_replace('/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~@.\[\]-]/', '', $email);
}

function is_email($email) {
    return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
}

function __($text, $domain = null) { return $text; }
function _n($single, $plural, $number, $domain = null) { return 1 === $number ? $single : $plural; }
function apply_filters($tag, $value) { return $value; }
function add_action() { return true; }
function add_filter() { return true; }
function get_option($key, $default = false) { return $GLOBALS['wp_options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['wp_options'][$key] = $value; return true; }

require_once dirname(__DIR__, 2) . '/includes/class-settings.php';

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

$reflection = new ReflectionClass('HRB_Settings');
$cache_prop = $reflection->getProperty('settings_cache');
$cache_prop->setAccessible(true);

/**
 * Build a settings instance driven purely by a seeded cache.
 *
 * HRB_Settings::get() returns early on a cache hit, so this exercises the real
 * recipient logic without a database.
 */
function settings_with(array $cache) {
    global $reflection, $cache_prop;

    $settings = $reflection->newInstanceWithoutConstructor();
    $cache_prop->setValue($settings, $cache);

    return $settings;
}

function recipients(array $cache): array {
    return settings_with($cache)->get_notification_recipients();
}

// ---------------------------------------------------------------------------
// Parsing
// ---------------------------------------------------------------------------

echo "\n-- hrb_parse_email_list --\n";

check('comma separated', hrb_parse_email_list('a@x.com, b@x.com'), ['a@x.com', 'b@x.com']);
check(
    'newlines, semicolons and stray whitespace',
    hrb_parse_email_list("a@x.com\n b@x.com ;c@x.com\r\n\r\n"),
    ['a@x.com', 'b@x.com', 'c@x.com']
);
check('empty string', hrb_parse_email_list(''), []);
check('separators only', hrb_parse_email_list(" ,;\n "), []);
check('array input', hrb_parse_email_list([' a@x.com ', '']), ['a@x.com']);

// ---------------------------------------------------------------------------
// Sanitizing
// ---------------------------------------------------------------------------

echo "\n-- hrb_sanitize_email_list --\n";

check('drops invalid entries', hrb_sanitize_email_list('a@x.com, nope, b@x.com'), 'a@x.com, b@x.com');
check(
    'de-duplicates case-insensitively and keeps the first casing',
    hrb_sanitize_email_list('Team@X.com, team@x.com, TEAM@X.COM'),
    'Team@X.com'
);
check(
    'normalises separators',
    hrb_sanitize_email_list("a@x.com\nb@x.com;c@x.com"),
    'a@x.com, b@x.com, c@x.com'
);
check('empty stays empty', hrb_sanitize_email_list(''), '');
check('all invalid becomes empty', hrb_sanitize_email_list('nope, also-nope'), '');

echo "\n-- hrb_invalid_emails_in_list --\n";

check('clean list has no offenders', hrb_invalid_emails_in_list('a@x.com, b@x.com'), []);
check('reports every offender', hrb_invalid_emails_in_list('oops, a@x.com, b@'), ['oops', 'b@']);
check('empty list is valid', hrb_invalid_emails_in_list(''), []);

// ---------------------------------------------------------------------------
// Setting schema and validation
// ---------------------------------------------------------------------------

echo "\n-- setting schema --\n";

$settings = settings_with([]);

$defaults_prop = $reflection->getProperty('default_settings');
$defaults_prop->setAccessible(true);
$schema = $defaults_prop->getValue($settings);

check('hrb_staff_emails is registered', isset($schema['hrb_staff_emails']), true);
check('type is email_list', $schema['hrb_staff_emails']['type'], 'email_list');
check('sanitize callback is a global function', function_exists($schema['hrb_staff_emails']['sanitize']), true);

$groups = $settings->get_settings_groups();
check('company tab offers the list', in_array('hrb_staff_emails', $groups['company']['settings'], true), true);
check('company tab hides the legacy field', in_array('hrb_staff_email', $groups['company']['settings'], true), false);

echo "\n-- validate_setting --\n";

check('empty list accepted', $settings->validate_setting('hrb_staff_emails', ''), true);
check('single address accepted', $settings->validate_setting('hrb_staff_emails', 'a@x.com'), true);
check('several addresses accepted', $settings->validate_setting('hrb_staff_emails', 'a@x.com, b@x.com, c@x.com'), true);
check('newline separated accepted', $settings->validate_setting('hrb_staff_emails', "a@x.com\nb@x.com"), true);
check(
    'one bad address is named in the error',
    $settings->validate_setting('hrb_staff_emails', 'a@x.com, oops'),
    'Invalid email address: oops'
);
check(
    'several bad addresses are all named',
    $settings->validate_setting('hrb_staff_emails', 'oops, b@'),
    'Invalid email addresses: oops, b@'
);
check(
    'the single-address type still rejects a list',
    $settings->validate_setting('hrb_admin_email', 'a@x.com, b@x.com'),
    'Invalid email address'
);

$sanitize_method = $reflection->getMethod('sanitize_setting');
$sanitize_method->setAccessible(true);
check(
    'save path dispatches to hrb_sanitize_email_list',
    $sanitize_method->invoke($settings, 'hrb_staff_emails', " a@x.com ;\n B@X.com , a@x.com"),
    'a@x.com, B@X.com'
);

// ---------------------------------------------------------------------------
// The daily-summary send time
// ---------------------------------------------------------------------------

echo "\n-- hrb_sanitize_time_of_day --\n";

check('a plain time', hrb_sanitize_time_of_day('08:30'), '08:30');
check('single digits are padded', hrb_sanitize_time_of_day('8:5'), '08:05');
check('seconds are dropped', hrb_sanitize_time_of_day('18:00:00'), '18:00');
check('whitespace is trimmed', hrb_sanitize_time_of_day('  23:59 '), '23:59');
check('midnight survives', hrb_sanitize_time_of_day('00:00'), '00:00');
check('an impossible hour is rejected', hrb_sanitize_time_of_day('25:00'), '');
check('an impossible minute is rejected', hrb_sanitize_time_of_day('12:75'), '');
check('junk is rejected', hrb_sanitize_time_of_day('lunchtime'), '');

echo "\n-- validate_setting: time --\n";

check('schema registers the time type', $schema['hrb_daily_summary_time']['type'], 'time');
check('the default is midnight', $schema['hrb_daily_summary_time']['default'], '00:00');
check('a valid time is accepted', $settings->validate_setting('hrb_daily_summary_time', '18:30'), true);
check('midnight is accepted', $settings->validate_setting('hrb_daily_summary_time', '00:00'), true);
check(
    'an out-of-range time is rejected',
    $settings->validate_setting('hrb_daily_summary_time', '25:00'),
    'Enter a time as HH:MM'
);
check(
    'junk is rejected',
    $settings->validate_setting('hrb_daily_summary_time', 'midnight'),
    'Enter a time as HH:MM'
);

check(
    'the summary recipients reuse the email-list type',
    $schema['hrb_daily_summary_emails']['type'],
    'email_list'
);
check(
    'summary recipients accept several addresses',
    $settings->validate_setting('hrb_daily_summary_emails', 'a@x.com, b@x.com'),
    true
);

// ---------------------------------------------------------------------------
// Who actually gets the mail
// ---------------------------------------------------------------------------

echo "\n-- get_notification_recipients --\n";

check(
    'admin only when the team list is switched off',
    recipients([
        'hrb_admin_email_notifications' => 1,
        'hrb_admin_email'               => 'admin@example.com',
        'hrb_staff_email_notifications' => 0,
        'hrb_staff_emails'              => 'a@x.com, b@x.com',
    ]),
    ['admin@example.com']
);

check(
    'admin plus the whole team list',
    recipients([
        'hrb_admin_email_notifications' => 1,
        'hrb_admin_email'               => 'admin@example.com',
        'hrb_staff_email_notifications' => 1,
        'hrb_staff_emails'              => 'a@x.com, b@x.com, c@x.com',
    ]),
    ['admin@example.com', 'a@x.com', 'b@x.com', 'c@x.com']
);

check(
    'team list only when admin notifications are off',
    recipients([
        'hrb_admin_email_notifications' => 0,
        'hrb_admin_email'               => 'admin@example.com',
        'hrb_staff_email_notifications' => 1,
        'hrb_staff_emails'              => 'a@x.com, b@x.com',
    ]),
    ['a@x.com', 'b@x.com']
);

check(
    'an empty admin address falls back to the WordPress admin email',
    recipients([
        'hrb_admin_email_notifications' => 1,
        'hrb_admin_email'               => '',
        'hrb_staff_email_notifications' => 0,
    ]),
    ['wpadmin@example.com']
);

check(
    'an address on both the admin field and the list is mailed once',
    recipients([
        'hrb_admin_email_notifications' => 1,
        'hrb_admin_email'               => 'Team@Example.com',
        'hrb_staff_email_notifications' => 1,
        'hrb_staff_emails'              => 'team@example.com, b@x.com',
    ]),
    ['Team@Example.com', 'b@x.com']
);

check(
    'a broken entry does not stop the valid ones',
    recipients([
        'hrb_admin_email_notifications' => 0,
        'hrb_staff_email_notifications' => 1,
        'hrb_staff_emails'              => 'a@x.com, garbage, b@x.com',
    ]),
    ['a@x.com', 'b@x.com']
);

check(
    'everything switched off yields no recipients',
    recipients([
        'hrb_admin_email_notifications' => 0,
        'hrb_staff_email_notifications' => 0,
        'hrb_staff_emails'              => 'a@x.com',
    ]),
    []
);

check(
    'team notifications on with an empty list still reaches the admin',
    recipients([
        'hrb_admin_email_notifications' => 1,
        'hrb_admin_email'               => 'admin@example.com',
        'hrb_staff_email_notifications' => 1,
        'hrb_staff_emails'              => '',
    ]),
    ['admin@example.com']
);

// ---------------------------------------------------------------------------
// Migration off the legacy single address
// ---------------------------------------------------------------------------

echo "\n-- migrate_staff_emails --\n";

$GLOBALS['wp_options']['hrb_staff_email'] = 'old-staff@example.com';
HRB_Settings::migrate_staff_emails();

check('legacy address moves onto the list', $GLOBALS['wp_options']['hrb_staff_emails'], 'old-staff@example.com');
check('migration is recorded', $GLOBALS['wp_options']['hrb_staff_emails_migrated'], 1);

// A removed address must stay removed.
$GLOBALS['wp_options']['hrb_staff_emails'] = '';
HRB_Settings::migrate_staff_emails();

check('a second pass cannot resurrect a removed address', $GLOBALS['wp_options']['hrb_staff_emails'], '');

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
