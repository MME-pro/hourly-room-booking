<?php
/**
 * Invoice PDF Generator
 * Handles PDF generation for invoices
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Invoice_Generator {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate PDF invoice
     */
    public function generate_invoice_pdf($invoice_id) {
        global $wpdb;
        
        // Get invoice data
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, b.*, r.name as room_name, c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.postal_code, c.country
             FROM {$wpdb->prefix}hrb_invoices i
             JOIN {$wpdb->prefix}hrb_bookings b ON i.booking_id = b.id
             JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE i.id = %d",
            $invoice_id
        ));
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found', 'hourly-room-booking'));
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/hrb-invoices';
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }
        
        // Generate PDF filename
        $filename = 'invoice-' . $invoice->invoice_number . '.pdf';
        $file_path = $invoice_dir . '/' . $filename;
        
        // Generate HTML content using the PDF generator
        $pdf_generator = HRB_PDF_Generator::getInstance();
        $html_content = $pdf_generator->generate_invoice_html($invoice->booking_id);
        
        if (!$html_content || empty(trim($html_content))) {
            return new WP_Error('html_generation_failed', __('Failed to generate HTML content', 'hourly-room-booking'));
        }
        
        // Generate PDF filename
        $pdf_filename = 'invoice-' . $invoice->invoice_number . '.pdf';
        $pdf_path = $invoice_dir . '/' . $pdf_filename;
        
        // Try to convert HTML to PDF
        $pdf_success = $pdf_generator->html_to_pdf($html_content, $pdf_path);
        
        if ($pdf_success && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            $file_path = $pdf_path;
        } else {
            // Fallback to HTML file
            $html_file = $invoice_dir . '/invoice-' . $invoice->invoice_number . '.html';
            $html_written = file_put_contents($html_file, $html_content);
            if ($html_written === false) {
                return new WP_Error('file_write_failed', __('Failed to create invoice file', 'hourly-room-booking'));
            }
            $file_path = $html_file;
        }
        
        // Verify file exists before updating database
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Invoice file was not created', 'hourly-room-booking'));
        }
        
        // Update invoice with PDF path
        $update_result = $wpdb->update(
            $wpdb->prefix . 'hrb_invoices',
            array('pdf_file_path' => $file_path),
            array('id' => $invoice_id),
            array('%s'),
            array('%d')
        );
        
        if ($update_result === false) {
            // Don't fail completely - file was created, just DB update failed
        }
        
        return $file_path;
    }
    
    
    /**
     * The bank account a cancellation fee is settled to
     *
     * Kept in one place so the invoice and the email cannot disagree.
     *
     * @since 1.7.3
     * @return array {
     *     @type string $holder
     *     @type string $iban
     *     @type string $bic
     * }
     */
    public static function get_bank_details() {
        return [
            'holder' => trim((string) get_option('hrb_bank_account_holder', '')),
            'iban'   => trim((string) get_option('hrb_bank_iban', '')),
            'bic'    => trim((string) get_option('hrb_bank_bic', '')),
        ];
    }

    /**
     * Build the PDF invoice for a booking's cancellation fee
     *
     * A separate document from the booking's own invoice: the fee is a distinct
     * charge, settled by bank transfer only. It is deliberately not written to
     * the invoices table — that table holds one row per booking and a second
     * row would make get_invoice_by_booking() ambiguous — so the number is
     * derived from the booking reference instead of the shared counter, which
     * also makes regenerating it idempotent.
     *
     * @since 1.7.3
     * @param int $booking_id Booking whose fee is being invoiced
     * @return string|WP_Error Absolute path to the generated file
     */
    public function generate_cancellation_fee_invoice($booking_id) {
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);

        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }

        $fee = isset($booking->cancellation_fee) ? floatval($booking->cancellation_fee) : 0;
        if ($fee <= 0) {
            return new WP_Error('no_cancellation_fee', __('This booking has no cancellation fee', 'hourly-room-booking'));
        }

        $upload_dir  = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/hrb-invoices';
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }

        $number = 'STORNO-' . date('Y') . '-' . $booking->booking_reference;
        $html   = $this->build_cancellation_fee_html($booking, $fee, $number);

        $pdf_path = $invoice_dir . '/cancellation-fee-' . sanitize_file_name($booking->booking_reference) . '.pdf';

        $pdf_generator = HRB_PDF_Generator::getInstance();
        $ok = $pdf_generator->html_to_pdf($html, $pdf_path);

        if ($ok && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            return $pdf_path;
        }

        // dompdf unavailable: an HTML file still gives the customer the figures
        // and the bank details, which is the point of the document.
        $html_path = $invoice_dir . '/cancellation-fee-' . sanitize_file_name($booking->booking_reference) . '.html';
        if (file_put_contents($html_path, $html) === false) {
            return new WP_Error('file_write_failed', __('Failed to create the cancellation fee invoice', 'hourly-room-booking'));
        }

        return $html_path;
    }

    /**
     * Markup for the cancellation-fee invoice
     *
     * @param object $booking Booking row
     * @param float  $fee     Fee amount
     * @param string $number  Invoice number
     * @return string
     */
    private function build_cancellation_fee_html($booking, $fee, $number) {
        $company_name  = get_option('hrb_company_name', get_bloginfo('name'));
        $company_addr  = get_option('hrb_company_address', '');
        $company_phone = get_option('hrb_company_phone', '');
        $company_email = get_option('hrb_company_email', get_option('admin_email'));
        $vat_id        = get_option('hrb_company_vat_id', '');
        $logo          = get_option('hrb_company_logo', '');

        $bank = self::get_bank_details();

        // The helper reads the name and address off the customer row for a
        // normal booking (the booking only carries them for anonymous ones), so
        // it has to be handed that row or the invoice goes out unaddressed.
        $customer_row = $booking->customer_id
            ? HRB_Customer_Manager::getInstance()->get_customer($booking->customer_id)
            : null;
        $customer = hrb_get_invoice_customer_info($booking, $customer_row);

        $date_format   = get_option('hrb_date_format', 'd.m.Y');
        $issue_date    = date_i18n($date_format, current_time('timestamp'));
        $due_date      = date_i18n($date_format, strtotime('+14 days', current_time('timestamp')));
        $booking_date  = date_i18n($date_format, strtotime($booking->booking_date));

        $logo_html = $logo
            ? '<img src="' . esc_url($logo) . '" alt="' . esc_attr($company_name) . '" style="max-width:200px;max-height:80px;">'
            : '<div style="font-size:20px;font-weight:bold;">' . esc_html($company_name) . '</div>';

        $bank_rows = '';
        if ($bank['holder'] !== '') {
            $bank_rows .= '<tr><td style="padding:4px 0;width:150px;">' . esc_html__('Account holder', 'hourly-room-booking')
                . '</td><td style="padding:4px 0;"><strong>' . esc_html($bank['holder']) . '</strong></td></tr>';
        }
        if ($bank['iban'] !== '') {
            $bank_rows .= '<tr><td style="padding:4px 0;">' . esc_html__('IBAN', 'hourly-room-booking')
                . '</td><td style="padding:4px 0;"><strong>' . esc_html($bank['iban']) . '</strong></td></tr>';
        }
        if ($bank['bic'] !== '') {
            $bank_rows .= '<tr><td style="padding:4px 0;">' . esc_html__('BIC', 'hourly-room-booking')
                . '</td><td style="padding:4px 0;"><strong>' . esc_html($bank['bic']) . '</strong></td></tr>';
        }
        $bank_rows .= '<tr><td style="padding:4px 0;">' . esc_html__('Reference', 'hourly-room-booking')
            . '</td><td style="padding:4px 0;"><strong>' . esc_html($booking->booking_reference) . '</strong></td></tr>';

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html($number) . '</title></head>';
        $html .= '<body style="font-family:Arial,sans-serif;color:#333;font-size:12px;padding:20px;">';

        // Header
        $html .= '<table width="100%" style="border-bottom:2px solid #eee;padding-bottom:16px;"><tr>';
        $html .= '<td style="vertical-align:top;">' . $logo_html . '</td>';
        $html .= '<td style="text-align:right;vertical-align:top;">';
        $html .= '<h1 style="margin:0;font-size:26px;color:#2c3e50;">' . esc_html__('Cancellation Fee Invoice', 'hourly-room-booking') . '</h1>';
        $html .= '<p style="margin:8px 0 0;">' . esc_html__('Invoice number', 'hourly-room-booking') . ': <strong>' . esc_html($number) . '</strong></p>';
        $html .= '<p style="margin:4px 0 0;">' . esc_html__('Issue date', 'hourly-room-booking') . ': ' . esc_html($issue_date) . '</p>';
        $html .= '<p style="margin:4px 0 0;">' . esc_html__('Due date', 'hourly-room-booking') . ': ' . esc_html($due_date) . '</p>';
        $html .= '</td></tr></table>';

        // Parties
        $html .= '<table width="100%" style="margin:24px 0;"><tr>';
        $html .= '<td style="width:50%;vertical-align:top;">';
        $html .= '<strong>' . esc_html__('From', 'hourly-room-booking') . '</strong><br>';
        $html .= esc_html($company_name) . '<br>' . nl2br(esc_html($company_addr)) . '<br>';
        $html .= esc_html($company_phone) . '<br>' . esc_html($company_email);
        if ($vat_id) {
            $html .= '<br>' . esc_html__('VAT ID', 'hourly-room-booking') . ': ' . esc_html($vat_id);
        }
        $html .= '</td>';
        $html .= '<td style="width:50%;vertical-align:top;">';
        $html .= '<strong>' . esc_html__('To', 'hourly-room-booking') . '</strong><br>';
        $html .= esc_html($customer['name']) . '<br>';
        if (!empty($customer['email'])) { $html .= esc_html($customer['email']) . '<br>'; }
        if (!empty($customer['address'])) { $html .= nl2br(esc_html($customer['address'])); }
        $html .= '</td></tr></table>';

        // The charge
        $html .= '<table width="100%" style="border-collapse:collapse;margin-top:10px;">';
        $html .= '<tr style="background:#f7f7f7;">';
        $html .= '<th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">' . esc_html__('Description', 'hourly-room-booking') . '</th>';
        $html .= '<th style="text-align:right;padding:10px;border-bottom:1px solid #ddd;width:120px;">' . esc_html__('Amount', 'hourly-room-booking') . '</th>';
        $html .= '</tr><tr>';
        $html .= '<td style="padding:10px;border-bottom:1px solid #eee;">'
            . esc_html__('Cancellation fee', 'hourly-room-booking') . '<br>'
            . '<small>' . sprintf(
                /* translators: 1: booking reference, 2: original booking date */
                esc_html__('Booking %1$s of %2$s', 'hourly-room-booking'),
                esc_html($booking->booking_reference),
                esc_html($booking_date)
            ) . '</small></td>';
        $html .= '<td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">' . esc_html(hrb_format_amount($fee)) . '</td>';
        $html .= '</tr><tr>';
        $html .= '<td style="padding:12px 10px;text-align:right;font-weight:bold;font-size:14px;">' . esc_html__('Total', 'hourly-room-booking') . '</td>';
        $html .= '<td style="padding:12px 10px;text-align:right;font-weight:bold;font-size:14px;color:#981b1e;">' . esc_html(hrb_format_amount($fee)) . '</td>';
        $html .= '</tr></table>';

        // How to pay — transfer only.
        $html .= '<div style="margin-top:28px;padding:16px;background:#f7f7f7;border-left:4px solid #981b1e;">';
        $html .= '<div style="font-weight:bold;margin-bottom:10px;">' . esc_html__('Please transfer the amount to', 'hourly-room-booking') . '</div>';
        $html .= '<table style="border-collapse:collapse;">' . $bank_rows . '</table>';
        $html .= '<div style="margin-top:12px;color:#981b1e;font-weight:bold;">'
            . esc_html__('Payment by PayPal is not accepted for the cancellation fee.', 'hourly-room-booking')
            . '</div>';
        $html .= '</div>';

        $html .= '<p style="margin-top:28px;font-size:11px;color:#666;">'
            . esc_html__('Please use the booking reference as the payment reference.', 'hourly-room-booking') . '</p>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get booking extras
     */
    private function get_booking_extras($booking_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT be.*, e.name, e.price
             FROM {$wpdb->prefix}hrb_booking_extras be
             JOIN {$wpdb->prefix}hrb_extras e ON be.extra_id = e.id
             WHERE be.booking_id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get invoice by booking ID
     */
    public function get_invoice_by_booking($booking_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_invoices WHERE booking_id = %d",
            $booking_id
        ));
    }
    
    /**
     * Update invoice data with latest booking information and regenerate PDF
     */
    public function regenerate_invoice($booking_id) {
        global $wpdb;
        
        // Get current booking data
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Get existing invoice
        $invoice = $this->get_invoice_by_booking($booking_id);
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found for this booking', 'hourly-room-booking'));
        }
        
        $tax_rate = floatval(get_option('hrb_tax_rate', 19));
        
        // Get total amount from payment records (source of truth)
        $payment_handler = HRB_Payment_Handler::getInstance();
        $all_payments = $payment_handler->get_booking_payments($booking_id);
        
        $total_amount_from_payments = 0;
        foreach ($all_payments as $payment) {
            $total_amount_from_payments += floatval($payment->amount);
        }
        
        // Fallback to booking table if no payment records exist
        if ($total_amount_from_payments == 0) {
            $total_amount_from_payments = floatval($booking->total_amount);
        }
        
        // Update invoice data with latest booking information
        $invoice_data = array(
            'subtotal' => $booking->base_price + $booking->extra_people_price + $booking->extras_price, // Include all base prices
            'tax_rate' => $tax_rate,
            'tax_amount' => $booking->tax_amount,
            'total_amount' => $total_amount_from_payments, // Use payment records as source of truth
            'issue_date' => current_time('Y-m-d')
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_invoices',
            $invoice_data,
            array('id' => $invoice->id),
            array('%f', '%f', '%f', '%f', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('invoice_update_failed', __('Failed to update invoice data', 'hourly-room-booking') . ': ' . $wpdb->last_error);
        }
        
        // Regenerate PDF
        $pdf_path = $this->generate_invoice_pdf($invoice->id);
        
        if (is_wp_error($pdf_path)) {
            return $pdf_path;
        }
        
        if (empty($pdf_path)) {
            return new WP_Error('pdf_generation_failed', __('Failed to generate PDF - no file path returned', 'hourly-room-booking'));
        }
        
        // Send invoice via email
        $email_result = $this->send_invoice_email($booking_id, $pdf_path);
        
        if (is_wp_error($email_result)) {
            // Don't fail the regeneration if email fails
        }
        
        return $pdf_path;
    }
    
    /**
     * Send invoice via email using template system
     */
    public function send_invoice_email($booking_id, $invoice_path = null) {
        // Get booking data using booking manager (includes customer email)
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Check if booking has email (skip anonymous bookings without email)
        if (empty($booking->email) || $booking->email === 'anonymous@example.com') {
            return new WP_Error('no_email', __('No email address available for this booking', 'hourly-room-booking'));
        }
        
        // Use notification manager to send email with template
        // Pass the invoice path so the notification manager can attach it
        $notification_manager = HRB_Notification_Manager::getInstance();
        $sent = $notification_manager->send_email_notification($booking, 'invoice_regenerated', array(
            'invoice_path' => $invoice_path
        ));
        
        if (!$sent) {
            return new WP_Error('email_send_failed', __('Failed to send invoice email', 'hourly-room-booking'));
        }
        
        return true;
    }
}
