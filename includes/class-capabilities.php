<?php
/**
 * Roles and capabilities
 *
 * Three roles run this plugin. A **Super Admin** is us — the people who build
 * and support the plugin — and is the only role shown the aggregate stats
 * headers that sit above a screen. An **Admin** is the client: settings, money,
 * exports, the whole business, minus those headers. An **Employee** runs the
 * desk — bookings, the calendar, customers, rooms and extras — and sees no
 * money at all. Not a total, not a price, not a revenue card, not a report.
 *
 * The money line is drawn by one capability, `hrb_view_financials`. Every
 * figure in the admin screens is behind it, so hiding a new one is a matter of
 * asking the same question rather than inventing another rule. It is a *view*
 * capability on purpose: an Employee still marks a booking paid, because that
 * is desk work, they just never see the sum involved.
 *
 * The role slug for an Employee is still `hrb_staff`, the name it had when it
 * was the only role and carried everything. Renaming the slug would unassign
 * every existing user; renaming what it *means* is the point of this file.
 *
 * A Super Admin is a plugin role rather than "whoever is a WordPress
 * administrator", because on a client site the client usually is one.
 *
 * @package HourlyRoomBooking
 * @since 1.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Capabilities {

    /**
     * Seeing a screen's stats header - the row of cards above the working
     * table that sums everything on it, rather than reporting one row.
     *
     * Separate from FINANCIALS on purpose. An Admin runs the business and
     * needs its money: a booking's price, a payment record, the reports
     * screen. What they are not given is the headline summary sitting above a
     * screen - "Total Revenue", "This Month" - which is our read on how the
     * installation is doing rather than theirs. Making that its own
     * capability leaves the client every figure they work with and takes only
     * the headline.
     *
     * Held by the Super Admin role alone. A WordPress administrator does not
     * get it: on a client site the client is often one.
     *
     * @since 1.18.0
     */
    const STATS = 'hrb_view_stats';

    /**
     * Seeing the books: revenue cards, totals across bookings, the figures on
     * the reports screen, price configuration, exports.
     *
     * This is the aggregate view - what the business took, not what a
     * customer owes. Admin only.
     */
    const FINANCIALS = 'hrb_view_financials';

    /**
     * Seeing what one booking costs.
     *
     * Desk work: the Amount column in the booking list, a booking's own
     * total and its payment records, the running total while taking a
     * booking. Someone at the desk has to be able to tell a customer what
     * they owe, and that is a different question from what the month took.
     *
     * @since 1.15.0
     */
    const BOOKING_AMOUNTS = 'hrb_view_booking_amounts';

    /**
     * Seeing bookings whose time is over.
     *
     * A booking from 06:00 to 07:00 is the desk's business until 07:00. At
     * 07:01 it is done with, and it drops off the Employee's booking list
     * and out of their payment list with it - what is left to do is the
     * books. An Admin keeps the whole history.
     *
     * Measured from the booking's *end*, not its date: a booking running
     * until 02:00 tomorrow is still live at 23:00 tonight.
     *
     * @since 1.17.0
     */
    const PAST_BOOKINGS = 'hrb_view_past_bookings';

    /**
     * Role slug for our own access: everything, stats headers included.
     *
     * @since 1.18.0
     */
    const ROLE_SUPER_ADMIN = 'hrb_super_admin';

    /**
     * Role slug for the client's full access.
     */
    const ROLE_ADMIN = 'hrb_admin';

    /**
     * Role slug for desk access. Kept from when it meant "everything".
     */
    const ROLE_EMPLOYEE = 'hrb_staff';

    /**
     * What an Employee may do: the whole desk, minus anything with a number on it.
     *
     * @since 1.13.0
     * @return string[]
     */
    public static function employee_caps() {
        return [
            'read',
            'hrb_view_bookings',
            'hrb_manage_bookings',   // includes marking a booking paid
            self::BOOKING_AMOUNTS,   // what this booking costs, not what the month took
            'hrb_view_payments',     // the payment list
            'hrb_manage_payments',   // and working it: view, complete, cancel, refund
            'hrb_view_calendar',
            'hrb_view_customers',
            'hrb_manage_customers',
            'hrb_manage_rooms',      // room diary and maintenance locks
            'hrb_view_extras',
            'hrb_manage_extras',     // extras stock and availability
        ];
    }

    /**
     * What an Admin may do: everything.
     *
     * @since 1.13.0
     * @return string[]
     */
    public static function admin_caps() {
        return array_merge(self::employee_caps(), [
            self::FINANCIALS,
            self::PAST_BOOKINGS,
            'hrb_view_reports',
            'hrb_manage_settings',
            'hrb_export_data',
        ]);
    }

    /**
     * What a Super Admin may do: everything an Admin may, plus the stats
     * headers.
     *
     * @since 1.18.0
     * @return string[]
     */
    public static function super_admin_caps() {
        return array_merge(self::admin_caps(), [
            self::STATS,
        ]);
    }

    /**
     * Every capability this plugin defines.
     *
     * @since 1.13.0
     * @return string[]
     */
    public static function all_caps() {
        return array_values(array_diff(self::super_admin_caps(), ['read']));
    }

    /**
     * The capabilities an Employee must not hold.
     *
     * Used to take back what the role was granted when it meant "everything" —
     * including capabilities written straight onto the user, which outrank the
     * role and would otherwise leave an Employee seeing every figure.
     *
     * @since 1.13.0
     * @return string[]
     */
    public static function employee_denied_caps() {
        return array_values(array_diff(self::all_caps(), self::employee_caps()));
    }

    /**
     * What to write onto an Admin, role or user.
     *
     * admin_caps() with 'read' taken out. 'read' belongs to WordPress, not to
     * this plugin: granting it to the administrator role would leave a
     * capability behind on deactivation, which only removes what all_caps()
     * names.
     *
     * @since 1.18.0
     * @return string[]
     */
    public static function admin_granted_caps() {
        return array_values(array_diff(self::all_caps(), self::admin_denied_caps()));
    }

    /**
     * The capabilities an Admin must not hold.
     *
     * Same reason as employee_denied_caps(): earlier versions wrote the full
     * set straight onto each Admin user, and a capability on the user outranks
     * the role, so dropping it from the role alone would leave every existing
     * Admin still seeing the stats headers.
     *
     * @since 1.18.0
     * @return string[]
     */
    public static function admin_denied_caps() {
        return array_values(array_diff(self::all_caps(), self::admin_caps()));
    }

    /**
     * May the current user see money?
     *
     * @since 1.13.0
     * @return bool
     */
    public static function can_view_financials() {
        return function_exists('current_user_can') && current_user_can(self::FINANCIALS);
    }

    /**
     * May the current user be shown a screen's stats header?
     *
     * @since 1.18.0
     * @return bool
     */
    public static function can_view_stats() {
        return function_exists('current_user_can') && current_user_can(self::STATS);
    }

    /**
     * May the current user be shown what a booking costs?
     *
     * @since 1.15.0
     * @return bool
     */
    /**
     * May the current user be shown bookings whose time is over?
     *
     * @since 1.17.0
     * @return bool
     */
    public static function can_view_past_bookings() {
        return function_exists('current_user_can') && current_user_can(self::PAST_BOOKINGS);
    }

    /**
     * A WHERE fragment that leaves finished bookings out, for whoever may
     * not see them.
     *
     * The boundary is built in SQL the same way becomes_past_at() builds it
     * in PHP: midnight after the day the booking finishes on, so a booking
     * stays on the desk's screens for the whole of its own day. "Now" is
     * passed in from PHP rather than taken from NOW(), because the database
     * server's clock and the plugin's timezone are not the same thing.
     *
     * @since 1.17.0
     * @param string $alias Table alias the bookings table is under
     * @param string $now   Y-m-d H:i:s to measure against
     * @return string SQL beginning with AND, or '' when everything is allowed
     */
    public static function unfinished_only_sql($alias = 'b', $now = null) {
        if (self::can_view_past_bookings()) {
            return '';
        }

        $now = $now === null ? date('Y-m-d H:i:s') : $now;

        return sprintf(
            ' AND ' . self::becomes_past_at_sql($alias) . " > '%s'",
            esc_sql($now)
        );
    }

    /**
     * When a booking finishes, as a SQL expression.
     *
     * The same rule booking_ends_at() applies in PHP: a row whose end does
     * not follow its start ran past midnight, so its end belongs to the next
     * day. Written once here because three separate places ask "is this
     * booking over?" - the capability filter, the no-show pass and the
     * completion pass - and a booking running 23:30 to 02:30 is only handled
     * right if all three agree.
     *
     * Compare it against a time handed in from PHP rather than NOW(): the
     * database server's clock and the plugin's timezone are not the same
     * thing.
     *
     * @since 1.18.0
     * @param string $alias Table alias the bookings table is under
     * @return string SQL expression yielding a DATETIME
     */
    public static function ended_at_sql($alias = 'b') {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);

        return "(CASE WHEN {$a}.end_time <= {$a}.start_time"
            . " THEN TIMESTAMP(DATE_ADD({$a}.booking_date, INTERVAL 1 DAY), {$a}.end_time)"
            . " ELSE TIMESTAMP({$a}.booking_date, {$a}.end_time) END)";
    }

    /**
     * When does a booking finish?
     *
     * A booking that runs past midnight is stored on the day it starts with
     * an end earlier than its start - 23:30 to 02:30 is one row on one date
     * - so the end rolls to the next day when it does not follow the start.
     *
     * @since 1.17.0
     * @param string $booking_date Y-m-d
     * @param string $start_time   H:i:s
     * @param string $end_time     H:i:s
     * @return string Y-m-d H:i:s
     */
    public static function booking_ends_at($booking_date, $start_time, $end_time) {
        $date = substr((string) $booking_date, 0, 10);
        $end  = substr((string) $end_time, 0, 8);

        if (strtotime($end) <= strtotime(substr((string) $start_time, 0, 8))) {
            $date = date('Y-m-d', strtotime($date . ' +1 day'));
        }

        return $date . ' ' . $end;
    }

    /**
     * When does a booking become a *past* booking?
     *
     * Not when it ends - at midnight after the day it ends on. A booking from
     * 06:00 to 07:00 is the desk's business for the whole of that day and
     * becomes past at 00:00 the next morning, rather than dropping out of
     * sight at 07:01 while the day it belongs to is still being worked.
     *
     * A booking that runs past midnight is measured from the day it actually
     * finishes on, not the day it started: 23:30 to 02:30 finishes on the
     * following day and becomes past at midnight after *that*. Keying it to
     * the start date would make a booking past at 00:00 while it was still
     * running.
     *
     * This is the single boundary everything downstream turns on - which
     * bookings the desk is shown, when a booking completes, and when an
     * unpaid one becomes a no-show - so those three can never disagree.
     *
     * @since 1.19.0
     * @param string $booking_date Y-m-d
     * @param string $start_time   H:i:s
     * @param string $end_time     H:i:s
     * @return string Y-m-d H:i:s at midnight
     */
    public static function becomes_past_at($booking_date, $start_time, $end_time) {
        $ends_on = substr(self::booking_ends_at($booking_date, $start_time, $end_time), 0, 10);

        return date('Y-m-d', strtotime($ends_on . ' +1 day')) . ' 00:00:00';
    }

    /**
     * When a booking becomes past, as a SQL expression.
     *
     * The PHP twin of becomes_past_at(): midnight after the day ended_at_sql()
     * lands on. Compare it against a time handed in from PHP rather than
     * NOW(), because the database server's clock and the plugin's timezone are
     * not the same thing.
     *
     * @since 1.19.0
     * @param string $alias Table alias the bookings table is under
     * @return string SQL expression yielding a DATETIME
     */
    public static function becomes_past_at_sql($alias = 'b') {
        return 'TIMESTAMP(DATE_ADD(DATE(' . self::ended_at_sql($alias) . '), INTERVAL 1 DAY))';
    }

    /**
     * Is this booking a past booking?
     *
     * The day it finishes on is its own: a 06:00-07:00 booking is still
     * current at 23:59 that night and past at 00:00, not past at 07:01. See
     * becomes_past_at() for where the boundary sits and why.
     *
     * Inclusive at midnight, because midnight is the first moment of the new
     * day rather than the last of the old one.
     *
     * @since 1.17.0
     * @param string      $booking_date Y-m-d
     * @param string      $start_time   H:i:s
     * @param string      $end_time     H:i:s
     * @param string|null $now          Y-m-d H:i:s; defaults to now
     * @return bool
     */
    public static function is_booking_passed($booking_date, $start_time, $end_time, $now = null) {
        $now = $now === null ? date('Y-m-d H:i:s') : $now;

        return self::becomes_past_at($booking_date, $start_time, $end_time) <= $now;
    }

    public static function can_view_booking_amounts() {
        return function_exists('current_user_can')
            && (current_user_can(self::BOOKING_AMOUNTS) || current_user_can(self::FINANCIALS));
    }

    /**
     * Is this booking date in the past?
     *
     * The calendar shows an Employee what a booking costs while it is still
     * ahead of them - they may still have to take the money - and stops once
     * the day has gone by, which is the takings rather than the desk's work.
     * An Admin sees both.
     *
     * @since 1.15.0
     * @param string $booking_date Y-m-d
     * @param string $today        Y-m-d; defaults to today
     * @return bool
     */
    public static function is_past_date($booking_date, $today = null) {
        $today = $today === null ? date('Y-m-d') : $today;

        return substr((string) $booking_date, 0, 10) < $today;
    }

    /**
     * May this booking's price be shown on the calendar?
     *
     * @since 1.15.0
     * @param string $booking_date Y-m-d
     * @param string $today        Y-m-d; defaults to today
     * @return bool
     */
    public static function can_view_calendar_amount($booking_date, $today = null) {
        if (self::can_view_financials()) {
            return true;
        }

        return self::can_view_booking_amounts() && !self::is_past_date($booking_date, $today);
    }
}
