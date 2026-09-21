<?php
/**
 * Roles and capabilities
 *
 * Two roles run this plugin. An **Admin** sees everything: settings, money and
 * exports. An **Employee** runs the desk — bookings, the calendar, customers,
 * rooms and extras — and sees no money at all. Not a total, not a price, not a
 * revenue card, not a report.
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
 * @package HourlyRoomBooking
 * @since 1.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Capabilities {

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
     * Role slug for full access.
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
            'hrb_view_reports',
            'hrb_manage_settings',
            'hrb_export_data',
        ]);
    }

    /**
     * Every capability this plugin defines.
     *
     * @since 1.13.0
     * @return string[]
     */
    public static function all_caps() {
        return array_values(array_diff(self::admin_caps(), ['read']));
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
     * May the current user see money?
     *
     * @since 1.13.0
     * @return bool
     */
    public static function can_view_financials() {
        return function_exists('current_user_can') && current_user_can(self::FINANCIALS);
    }

    /**
     * May the current user be shown what a booking costs?
     *
     * @since 1.15.0
     * @return bool
     */
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
