<?php
/**
 * Tests for the 15-minute arrival reminder to the team.
 *
 * The behaviour (who is reminded, the wording, that it is never sent twice) was
 * exercised end-to-end against the real schema. What is pinned here is the part
 * that is easy to break again silently: the reminder windows must be built from
 * WordPress' clock, not the database server's.
 *
 * Booking times are stored in the site's timezone; MySQL's NOW() is whatever
 * the database server is set to. On the development machine those were three
 * hours apart, which sent every reminder at the wrong time — the arrival
 * reminder never fired at all until this was fixed.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-arrival-reminder.php
 *
 * @package HourlyRoomBooking
 * @since 1.9.0
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

$notifications = file_get_contents(HRB_PLUGIN_DIR . 'includes/class-notification-manager.php');
$bookings      = file_get_contents(HRB_PLUGIN_DIR . 'includes/class-booking-manager.php');
$templates     = file_get_contents(HRB_PLUGIN_DIR . 'includes/email-templates-data.php');
$bootstrap     = file_get_contents(HRB_PLUGIN_DIR . 'hourly-room-booking.php');

// ---------------------------------------------------------------------------
// The clock the windows are built from
// ---------------------------------------------------------------------------

echo "\n-- reminder windows use the site clock --\n";

// Isolate the two reminder queries and check neither compares against NOW().
function reminder_query(string $source, string $marker): string {
    $at = strpos($source, $marker);
    return $at === false ? '' : substr($source, $at, 2600);
}

$arrival  = reminder_query($notifications, 'function send_arrival_reminders');
$customer = reminder_query($bookings, 'function send_booking_reminders');

check('the arrival reminder query was found', $arrival !== '', true);
check('the customer reminder query was found', $customer !== '', true);

check(
    'the arrival reminder does not compare against MySQL NOW()',
    (bool) preg_match('/NOW\(\)\s*\+\s*INTERVAL/', $arrival),
    false
);

check(
    'the arrival reminder builds its window from current_time()',
    (bool) preg_match('/current_time\(\s*[\'"]timestamp[\'"]\s*\)/', $arrival),
    true
);

check(
    'the customer reminder does not compare against MySQL NOW() either',
    (bool) preg_match('/NOW\(\)\s*\+\s*INTERVAL/', $customer),
    false
);

check(
    'the customer reminder builds its window from current_time()',
    (bool) preg_match('/current_time\(\s*[\'"]timestamp[\'"]\s*\)/', $customer),
    true
);

// ---------------------------------------------------------------------------
// Sending once, and only once
// ---------------------------------------------------------------------------

echo "\n-- de-duplication --\n";

// send_admin_email() logs "<event>_admin"; the dedupe check has to look for the
// same string or every cron tick would send the reminder again.
check(
    'the reminder is sent with the arrival_reminder event',
    (bool) preg_match('/send_admin_email\([^)]*[\'"]arrival_reminder[\'"]\)/', $arrival . $notifications),
    true
);

check(
    'and de-duplicated on the logged name arrival_reminder_admin',
    (bool) preg_match('/event = [\'"]arrival_reminder_admin[\'"]/', $arrival),
    true
);

check(
    'the log event is derived as <event>_admin',
    (bool) preg_match("/event \. '_admin'/", $notifications),
    true
);

// ---------------------------------------------------------------------------
// Who is reminded
// ---------------------------------------------------------------------------

echo "\n-- scope --\n";

check('only confirmed bookings', strpos($arrival, "status = 'confirmed'") !== false, true);
check('anonymous blocks are skipped', strpos($arrival, 'is_anonymous = 0') !== false, true);

check(
    'it goes to the same addresses as a new-booking notification',
    strpos($notifications, 'get_notification_recipients') !== false,
    true
);

// ---------------------------------------------------------------------------
// Scheduling
// ---------------------------------------------------------------------------

echo "\n-- scheduling --\n";

check(
    'the job runs every five minutes',
    (bool) preg_match("/wp_schedule_event\(.{0,60}'hrb_five_minutes'.{0,60}ARRIVAL_CRON/s", $notifications),
    true
);

check(
    'the lead time is fifteen minutes',
    (bool) preg_match('/ARRIVAL_LEAD_MINUTES\s*=\s*15/', $notifications),
    true
);

// A five-minute cron against a ten-minute window: a booking cannot slip past
// unseen even when a tick is missed.
check(
    'the window is wider than the cron interval',
    (bool) preg_match('/\$lead\s*-\s*5.*\$lead\s*\+\s*5/s', $arrival),
    true
);

check(
    'the cron is cleared on deactivation',
    strpos($bootstrap, "wp_clear_scheduled_hook('hrb_send_arrival_reminders')") !== false,
    true
);

// ---------------------------------------------------------------------------
// The message
// ---------------------------------------------------------------------------

echo "\n-- the branded template --\n";

check('an arrival reminder template is bundled', strpos($templates, 'arrival_reminder_admin') !== false, true);

$at = strpos($templates, 'arrival_reminder_admin');
$template = substr($templates, $at, 4000);

foreach (['{customer_name}', '{room_name}', '{booking_reference}', '{start_time}', '{company_logo_html}'] as $token) {
    check("the template carries {$token}", strpos($template, $token) !== false, true);
}

check('the subject says 15 minutes', strpos($template, 'In 15 Minuten') !== false, true);

// Adding a template to the bundle only reaches existing sites when the bundle
// version changes; without it the reminder would fall back to the plain text.
check(
    'the template bundle version was bumped so the new template is seeded',
    (bool) preg_match("/bundle_version = '2026-09-05-arrival-reminder'/", file_get_contents(HRB_PLUGIN_DIR . 'includes/class-database.php')),
    true
);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
