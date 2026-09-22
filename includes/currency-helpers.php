<?php
/**
 * Currency Helper Functions
 * Provides easy-to-use functions for currency operations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get currency symbol
 */
function hrb_get_currency_symbol(): string {
    return HRB_Currency_Manager::getInstance()->get_currency_symbol();
}

/**
 * Format amount with currency
 */
function hrb_format_amount(float $amount, bool $show_symbol = true): string {
    return HRB_Currency_Manager::getInstance()->format_amount($amount, $show_symbol);
}

/**
 * May the current user be shown money?
 *
 * The admin screens ask this before printing any figure: a total, a
 * price, a revenue card, a payment record. An Employee runs the desk
 * without seeing sums; an Admin sees everything. See HRB_Capabilities.
 *
 * Deliberately not built into hrb_format_amount(): the customer-facing
 * pages, the invoices and the emails all format money for people who are
 * not logged in at all, and must keep doing so.
 *
 * @since 1.13.0
 */
function hrb_can_view_financials(): bool {
    return HRB_Capabilities::can_view_financials();
}

/**
 * May the current user be shown a screen's stats header?
 *
 * The row of summary cards above a working table - what the month took,
 * how many transactions there have been - as opposed to the figures in
 * the table itself. That headline is ours: a client Admin still sees and
 * works every payment on the Payments screen, they are just not given the
 * totals across the top. See HRB_Capabilities::STATS.
 *
 * @since 1.18.0
 */
function hrb_can_view_stats(): bool {
    return HRB_Capabilities::can_view_stats();
}

/**
 * May the current user be shown what a single booking costs?
 *
 * The desk's question - what does this customer owe - as opposed to the
 * books, which is hrb_can_view_financials(). An Employee gets this one.
 *
 * @since 1.15.0
 */
function hrb_can_view_booking_amounts(): bool {
    return HRB_Capabilities::can_view_booking_amounts();
}

/**
 * May this booking's price be shown on the calendar?
 *
 * An Employee sees it while the booking is still ahead of them and not
 * once the day has passed; an Admin sees both.
 *
 * @since 1.15.0
 * @param string $booking_date Y-m-d
 */
function hrb_can_view_calendar_amount($booking_date): bool {
    return HRB_Capabilities::can_view_calendar_amount($booking_date);
}

/**
 * Get currency code
 */
function hrb_get_currency_code(): string {
    return HRB_Currency_Manager::getInstance()->get_currency_code();
}

/**
 * Get currency data
 */
function hrb_get_currency_data(string $currency_code = null): array {
    return HRB_Currency_Manager::getInstance()->get_currency_data($currency_code);
}

/**
 * Get translated payment method label
 */
function hrb_get_payment_method_label(string $payment_method): string {
    $method_labels = array(
        'paypal' => __('PayPal', 'hourly-room-booking'),
        'onsite' => __('On-site Payment', 'hourly-room-booking'),
        'stripe' => __('Stripe', 'hourly-room-booking'),
        'bank_transfer' => __('Bank Transfer', 'hourly-room-booking'),
        'cash' => __('Cash', 'hourly-room-booking')
    );
    
    return isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst(str_replace('_', ' ', $payment_method));
}

/**
 * Is bank transfer switched on at all?
 *
 * The one setting behind both halves of it: the payment method an admin may
 * pick, and the "Paid by bank transfer" note on the booking forms.
 *
 * @since 1.18.0
 */
function hrb_bank_transfer_enabled(): bool {
    return (bool) HRB_Settings::getInstance()->get('hrb_bank_transfer_enabled');
}

/**
 * Payment methods that exist only behind the admin screens.
 *
 * Bank transfer is settled by hand — someone reads the account statement and
 * marks the booking paid — so it is desk work, and the public booking flow is
 * never offered it.
 *
 * This list is fixed: it says what a method *is*, not whether it is currently
 * on offer. A booking taken by transfer keeps its method even after the option
 * is switched off in the settings.
 *
 * @since 1.19.0
 * @return string[]
 */
function hrb_get_backend_only_payment_methods(): array {
    return array('bank_transfer');
}

/**
 * Is this method one an admin may pick but a customer may not?
 *
 * @since 1.19.0
 */
function hrb_is_backend_only_payment_method(string $payment_method): bool {
    return in_array(strtolower(trim($payment_method)), hrb_get_backend_only_payment_methods(), true);
}

/**
 * The payment methods a booking form may offer, as key => translated label.
 *
 * Callers that render or accept a *customer's* choice ask for the public list,
 * which is the default. The admin booking forms — and the validation behind
 * them — pass true. That single flag is what keeps bank transfer off the front
 * end; nothing in the public templates or the public AJAX path ever sets it.
 *
 * @since 1.19.0
 * @param bool $include_backend_only Also return the admin-only methods.
 * @return array<string,string>
 */
function hrb_get_selectable_payment_methods(bool $include_backend_only = false): array {
    $methods = array(
        'onsite' => hrb_get_payment_method_label('onsite'),
        'paypal' => hrb_get_payment_method_label('paypal'),
    );

    if ($include_backend_only && hrb_bank_transfer_enabled()) {
        $methods['bank_transfer'] = hrb_get_payment_method_label('bank_transfer');
    }

    return $methods;
}

/**
 * The account a booking marked as paid by transfer was paid into.
 *
 * Empty fields fall back to the company bank details under Settings → Company
 * Information, which is where the cancellation-fee invoice already reads them
 * from, so a site that has filled those in does not have to type the IBAN a
 * second time.
 *
 * @since 1.18.0
 * @return array{bank_name:string,account_holder:string,iban:string,bic:string,reference:string,instructions:string}
 */
function hrb_get_bank_transfer_details(): array {
    $settings = HRB_Settings::getInstance();

    $read = static function (string $key, string $fallback_key = '') use ($settings): string {
        $value = trim((string) $settings->get($key, ''));
        if ($value === '' && $fallback_key !== '') {
            $value = trim((string) $settings->get($fallback_key, ''));
        }
        return $value;
    };

    return array(
        'bank_name'      => $read('hrb_bank_transfer_bank_name'),
        'account_holder' => $read('hrb_bank_transfer_account_holder', 'hrb_bank_account_holder'),
        'iban'           => $read('hrb_bank_transfer_iban', 'hrb_bank_iban'),
        'bic'            => $read('hrb_bank_transfer_bic', 'hrb_bank_bic'),
        'reference'      => $read('hrb_bank_transfer_reference'),
        'instructions'   => $read('hrb_bank_transfer_instructions'),
    );
}

/**
 * The payment reference to quote on the transfer.
 *
 * The setting is a template; `{booking_reference}` is replaced with the
 * booking's own reference. On the "add booking" form there is no reference
 * yet, so the placeholder is left standing rather than blanked out.
 *
 * @since 1.18.0
 */
function hrb_get_bank_transfer_reference(string $booking_reference = ''): string {
    $details = hrb_get_bank_transfer_details();

    if ($details['reference'] === '' || $booking_reference === '') {
        return $details['reference'];
    }

    return str_replace('{booking_reference}', $booking_reference, $details['reference']);
}

/**
 * Get translated payment status label
 */
function hrb_get_payment_status_label(string $status): string {
    $status_labels = array(
        'pending' => __('Pending', 'hourly-room-booking'),
        'completed' => __('Completed', 'hourly-room-booking'),
        'cancelled' => __('Cancelled', 'hourly-room-booking'),
        'failed' => __('Failed', 'hourly-room-booking'),
        'refunded' => __('Refunded', 'hourly-room-booking'),
        'partially_refunded' => __('Partially Refunded', 'hourly-room-booking'),
        'nil' => __('Nil', 'hourly-room-booking')
    );
    
    return isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get translated booking status label
 */
function hrb_get_booking_status_label(string $status): string {
    $status_labels = array(
        'pending' => __('Pending', 'hourly-room-booking'),
        'confirmed' => __('Confirmed', 'hourly-room-booking'),
        'completed' => __('Completed', 'hourly-room-booking'),
        'cancelled' => __('Cancelled', 'hourly-room-booking'),
        'no_show' => __('No Show', 'hourly-room-booking')
    );
    
    return isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get German day abbreviation
 */
function hrb_get_german_day_abbreviation(string $date): string {
    $day_number = date('w', strtotime($date)); // 0 = Sunday, 1 = Monday, etc.
    
    $german_days = array(
        0 => 'So', // Sunday
        1 => 'Mo', // Monday
        2 => 'Di', // Tuesday
        3 => 'Mi', // Wednesday
        4 => 'Do', // Thursday
        5 => 'Fr', // Friday
        6 => 'Sa'  // Saturday
    );
    
    return $german_days[$day_number] ?? 'So';
}
