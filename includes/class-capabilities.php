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
     * Seeing money: totals, prices, revenue, payments, reports, exports.
     */
    const FINANCIALS = 'hrb_view_financials';

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
            'hrb_view_payments',
            'hrb_manage_payments',
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
}
