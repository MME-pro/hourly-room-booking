<?php
/**
 * Tests for the Admin/Employee split and the report date ranges.
 *
 * An Employee runs the desk and never sees money. That line is drawn by one
 * capability, hrb_view_financials, and the role maps here are what put every
 * screen on the right side of it. The denied list matters most: the role used
 * to carry everything, and those capabilities were written onto each user as
 * well, so they have to be named to be taken back.
 *
 * The date ranges belong to the export that was returning a bare 0. The
 * Reports screen and its export have to land on the same two dates, so the
 * rule is pinned here rather than trusted twice.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-roles-and-exports.php
 *
 * @package HourlyRoomBooking
 * @since 1.13.0
 */

define('ABSPATH', __DIR__);

function add_action() { return true; }
function add_filter() { return true; }
function __($text, $domain = null) { return $text; }

require_once dirname(__DIR__, 2) . '/includes/class-capabilities.php';
require_once dirname(__DIR__, 2) . '/includes/class-report-exporter.php';

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

function employee_has(string $cap): bool {
    return in_array($cap, HRB_Capabilities::employee_caps(), true);
}

function admin_has(string $cap): bool {
    return in_array($cap, HRB_Capabilities::admin_caps(), true);
}

// ---------------------------------------------------------------------------
// What an Employee may do
// ---------------------------------------------------------------------------

echo "\n-- the desk --\n";

check('sees bookings', employee_has('hrb_view_bookings'), true);
check('takes and edits bookings', employee_has('hrb_manage_bookings'), true);
check('sees the calendar', employee_has('hrb_view_calendar'), true);
check('handles customers', employee_has('hrb_manage_customers'), true);
check('keeps the room diary', employee_has('hrb_manage_rooms'), true);
check('keeps extras in stock', employee_has('hrb_manage_extras'), true);
check('sees what a booking costs', employee_has(HRB_Capabilities::BOOKING_AMOUNTS), true);
check('sees the payments list', employee_has('hrb_view_payments'), true);
check('but not bookings whose time is over', employee_has(HRB_Capabilities::PAST_BOOKINGS), false);
check('and works it: view, complete, cancel, refund', employee_has('hrb_manage_payments'), true);

echo "\n-- but never the money --\n";

check('not the books', employee_has(HRB_Capabilities::FINANCIALS), false);
check('no reports', employee_has('hrb_view_reports'), false);
check('no settings', employee_has('hrb_manage_settings'), false);
check('no exports', employee_has('hrb_export_data'), false);

// ---------------------------------------------------------------------------
// What an Admin may do
// ---------------------------------------------------------------------------

echo "\n-- an Admin has the lot --\n";

$missing_from_admin = array_values(array_diff(HRB_Capabilities::all_caps(), HRB_Capabilities::admin_caps()));
check('nothing is withheld from an Admin', $missing_from_admin, []);
check('including the figures', admin_has(HRB_Capabilities::FINANCIALS), true);
check('and everything the desk has', array_values(array_diff(HRB_Capabilities::employee_caps(), HRB_Capabilities::admin_caps())), []);

// ---------------------------------------------------------------------------
// What has to be taken back from an existing Employee
// ---------------------------------------------------------------------------

echo "\n-- revoking the old grants --\n";

$denied = HRB_Capabilities::employee_denied_caps();
sort($denied);

check('exactly the books are revoked, not the desk', $denied, [
    'hrb_export_data',
    'hrb_manage_settings',
    'hrb_view_financials',
    'hrb_view_past_bookings',
    'hrb_view_reports',
]);

check('nothing the desk needs is revoked',
    array_values(array_intersect($denied, HRB_Capabilities::employee_caps())), []);

check('"read" is not treated as a plugin capability',
    in_array('read', HRB_Capabilities::all_caps(), true), false);

// ---------------------------------------------------------------------------
// Past or not: what the calendar shows an Employee
// ---------------------------------------------------------------------------

echo "
-- when a booking is done with --
";

// 06:00-07:00 on the 21st: still the desk's business at 07:00, finished at
// 07:01. The boundary is the end itself, and the end counts as live.
function passed(string $date, string $start, string $end, string $now): bool {
    return HRB_Capabilities::is_booking_passed($date, $start, $end, $now);
}

check('while it is still running', passed('2026-09-21', '06:00:00', '07:00:00', '2026-09-21 06:30:00'), false);
check('at the very end it is not yet passed', passed('2026-09-21', '06:00:00', '07:00:00', '2026-09-21 07:00:00'), false);
check('a minute later it is', passed('2026-09-21', '06:00:00', '07:00:00', '2026-09-21 07:01:00'), true);
check('before it even starts', passed('2026-09-21', '06:00:00', '07:00:00', '2026-09-21 05:00:00'), false);
check('a booking on a later day is not passed', passed('2026-09-22', '06:00:00', '07:00:00', '2026-09-21 23:59:00'), false);

// The case a date comparison gets wrong: a booking that started last night
// and is still running now.
check('one running past midnight is live at 01:00',
    passed('2026-09-21', '23:30:00', '02:30:00', '2026-09-22 01:00:00'), false);
check('...and done at 02:31',
    passed('2026-09-21', '23:30:00', '02:30:00', '2026-09-22 02:31:00'), true);
check('its end rolls to the next day',
    HRB_Capabilities::booking_ends_at('2026-09-21', '23:30:00', '02:30:00'), '2026-09-22 02:30:00');
check('an ordinary booking ends on its own day',
    HRB_Capabilities::booking_ends_at('2026-09-21', '06:00:00', '07:00:00'), '2026-09-21 07:00:00');

echo "
-- a booking's day, against today --
";

// The calendar shows an Employee what a booking costs while it is still
// ahead of them, and stops once the day has gone by. Reckoned from
// 2026-09-21 throughout.
const TODAY = '2026-09-21';

function past(string $date): bool {
    return HRB_Capabilities::is_past_date($date, TODAY);
}

check('yesterday is past', past('2026-09-20'), true);
check('today is not', past('2026-09-21'), false);
check('tomorrow is not', past('2026-09-22'), false);
check('last month is', past('2026-08-31'), true);
check('next year is not', past('2027-01-01'), false);
check('a datetime is read by its date', past('2026-09-20 23:59:59'), true);
check("...and today's datetime still is not past", past('2026-09-21 00:00:00'), false);

// ---------------------------------------------------------------------------
// Report ranges: the screen and its export must agree
// ---------------------------------------------------------------------------

echo "\n-- report date ranges --\n";

// Reckoned from Tuesday 2026-09-15, mid-month, so a month boundary is visible.
const NOW = 1789430400; // 2026-09-15 00:00:00 UTC

function report_range(string $r, string $from = '', string $to = ''): array {
    return HRB_Report_Exporter::date_range($r, $from, $to, NOW);
}

check('this month runs first to last', report_range('this_month'), ['2026-09-01', '2026-09-30']);
check('last month too', report_range('last_month'), ['2026-08-01', '2026-08-31']);
check('this year', report_range('this_year'), ['2026-01-01', '2026-12-31']);
check('7 days counts back from today', report_range('7_days'), ['2026-09-08', '2026-09-15']);
check('30 days', report_range('30_days'), ['2026-08-16', '2026-09-15']);
check('90 days', report_range('90_days'), ['2026-06-17', '2026-09-15']);
check('a custom range is taken as given', report_range('custom', '2026-03-04', '2026-03-09'), ['2026-03-04', '2026-03-09']);
check('a half-filled custom range falls back to this month', report_range('custom', '2026-03-04', ''), ['2026-09-01', '2026-09-30']);
check('an unknown range falls back to this month', report_range('whatever'), ['2026-09-01', '2026-09-30']);

// The 31st is the case that breaks a naive "-1 month": subtracting a month
// from 31 August lands on 31 July, not in the month meant.
check('last month from a 31-day month still lands whole',
    HRB_Report_Exporter::date_range('last_month', '', '', strtotime('2026-03-31 12:00:00 UTC')),
    ['2026-02-01', '2026-02-28']);

// ---------------------------------------------------------------------------

echo "\n";
if ($failures > 0) {
    printf("%d FAILED\n", $failures);
    exit(1);
}

echo "ALL PASSED\n";
