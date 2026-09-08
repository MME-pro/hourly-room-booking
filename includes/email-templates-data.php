<?php
/**
 * Auto-generated branded email templates (de_DE).
 * Loaded once by HRB_Database::seed_branded_email_templates() to sync the
 * wp_hrb_email_templates table on plugin update. Regenerate by exporting the
 * hrb_email_templates table.
 */
if (!defined('ABSPATH')) { exit; }
return array (
  0 => 
  array (
    'template_key' => 'booking_confirmation_user',
    'template_type' => 'user',
    'template_name' => 'Booking Confirmation (User)',
    'subject' => 'Booking Confirmed - {booking_reference}',
    'heading' => 'Booking Confirmed!',
    'message' => 'Thank you for your booking. Here are your booking details:',
    'html_content' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Buchung bestätigt</title><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333333;margin:0;padding:0;background-color:#f7f7f7;} .container{max-width:600px;margin:20px auto;background:#ffffff;padding:30px;border:1px solid #e5e5e5;} .header{background-color:#ffffff;padding:20px 0;text-align:center;border-bottom:1px solid #e5e5e5;} .header img{max-width:250px;height:auto;} .content{padding:30px 0 10px 0;} .content p{margin:0 0 15px 0;} .booking-details{margin:25px 0;border:1px solid #e5e5e5;width:100%;border-collapse:collapse;} .booking-details th,.booking-details td{padding:12px 15px;text-align:left;border-bottom:1px solid #e5e5e5;vertical-align:top;} .booking-details th{background-color:#f7f7f7;width:140px;font-weight:600;color:#555;} .success-box{background:#f0f9eb;padding:20px;margin:0 0 25px 0;border-left:4px solid #28a745;text-align:center;} .success-box h2{margin:0;color:#1e7e34;font-size:20px;} .update-box{background:#eaf4fc;padding:20px;margin:0 0 25px 0;border-left:4px solid #2c8bd6;text-align:center;} .update-box h2{margin:0;color:#1f6fb0;font-size:20px;} .amount-row td{background-color:#fffcf5;color:#981b1e;font-weight:bold;font-size:16px;} .btn-container{text-align:center;margin:35px 0;} .btn{background-color:#981b1e;color:#ffffff !important;padding:15px 30px;text-decoration:none;font-weight:bold;border-radius:4px;display:inline-block;font-size:16px;} .footer{margin-top:30px;padding-top:20px;border-top:1px solid #e5e5e5;font-size:12px;color:#666666;text-align:center;}</style></head><body><div class="container"><div class="header">{company_logo_html}</div><div class="content"><p>Liebe/r {customer_name},</p><p>vielen Dank für Ihre Buchung. Wir freuen uns, Ihre Reservierung bestätigen zu können.</p><div class="success-box"><h2>Buchung bestätigt</h2></div><p>Nachfolgend finden Sie die Details Ihrer Reservierung:</p><table class="booking-details"><tr><th>Buchungsnummer</th><td><strong>{booking_reference}</strong></td></tr><tr><th>Raum</th><td>{room_name}</td></tr><tr><th>Datum / Zeit</th><td>{booking_date}<br><small>{start_time} – {end_time}</small></td></tr><tr><th>Dauer</th><td>{duration}</td></tr><tr><th>Zahlungsart</th><td>{payment_method}</td></tr><tr><th>Status</th><td>{booking_status}</td></tr><tr class="amount-row"><th>Gesamtbetrag</th><td>{total_amount}</td></tr></table><div class="btn-container"><a href="{booking_url}" class="btn">Buchung ansehen</a></div><p style="margin-top:30px;">Wir freuen uns auf Ihren Besuch. Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p></div><div class="footer"><p><strong>{company_name}</strong><br>{company_phone} | {company_email}</p></div></div></body></html>',
  ),
  1 => 
  array (
    'template_key' => 'payment_confirmation_user',
    'template_type' => 'user',
    'template_name' => 'Payment Confirmation (User)',
    'subject' => 'Zahlung bestätigt - {booking_reference}',
    'heading' => 'Zahlung erhalten',
    'message' => 'Your payment has been successfully processed.',
    'html_content' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Zahlung erhalten</title><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333333;margin:0;padding:0;background-color:#f7f7f7;} .container{max-width:600px;margin:20px auto;background:#ffffff;padding:30px;border:1px solid #e5e5e5;} .header{background-color:#ffffff;padding:20px 0;text-align:center;border-bottom:1px solid #e5e5e5;} .header img{max-width:250px;height:auto;} .content{padding:30px 0 10px 0;} .content p{margin:0 0 15px 0;} .booking-details{margin:25px 0;border:1px solid #e5e5e5;width:100%;border-collapse:collapse;} .booking-details th,.booking-details td{padding:12px 15px;text-align:left;border-bottom:1px solid #e5e5e5;vertical-align:top;} .booking-details th{background-color:#f7f7f7;width:140px;font-weight:600;color:#555;} .success-box{background:#f0f9eb;padding:20px;margin:0 0 25px 0;border-left:4px solid #28a745;text-align:center;} .success-box h2{margin:0;color:#1e7e34;font-size:20px;} .amount-row td{background-color:#fffcf5;color:#981b1e;font-weight:bold;font-size:16px;} .btn-container{text-align:center;margin:35px 0;} .btn{background-color:#981b1e;color:#ffffff !important;padding:15px 30px;text-decoration:none;font-weight:bold;border-radius:4px;display:inline-block;font-size:16px;} .footer{margin-top:30px;padding-top:20px;border-top:1px solid #e5e5e5;font-size:12px;color:#666666;text-align:center;}</style></head><body><div class="container"><div class="header">{company_logo_html}</div><div class="content"><p>Liebe/r {customer_name},</p><p>vielen Dank für Ihre Zahlung. Wir bestätigen hiermit den Eingang Ihrer Zahlung.</p><div class="success-box"><h2>Zahlung erhalten</h2></div><table class="booking-details"><tr><th>Buchungsnummer</th><td><strong>{booking_reference}</strong></td></tr><tr><th>Raum</th><td>{room_name}</td></tr><tr><th>Datum / Zeit</th><td>{booking_date}<br><small>{start_time} – {end_time}</small></td></tr><tr><th>Dauer</th><td>{duration}</td></tr><tr><th>Zahlungsart</th><td>{payment_method}</td></tr><tr><th>Status</th><td>{booking_status}</td></tr><tr class="amount-row"><th>Gezahlter Betrag</th><td>{total_amount}</td></tr></table><div class="btn-container"><a href="{booking_url}" class="btn">Buchung ansehen</a></div><p style="margin-top:30px;">Ihre Buchung ist nun vollständig bezahlt. Wir freuen uns auf Ihren Besuch.</p></div><div class="footer"><p><strong>{company_name}</strong><br>{company_phone} | {company_email}</p></div></div></body></html>',
  ),
  2 => 
  array (
    'template_key' => 'booking_confirmation_admin',
    'template_type' => 'admin',
    'template_name' => 'Booking Confirmation (Admin)',
    'subject' => 'Neue Buchung erhalten - {booking_reference}',
    'heading' => 'Neue Buchung!',
    'message' => 'A new booking has been received. Here are the details:',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Neue Buchung erhalten</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        h3 {
            margin-top: 25px;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 2px solid #f7f7f7;
            padding-bottom: 5px;
        }
        /* Success Box - Green for New Revenue/Booking */
        .alert-box {
            background: #f0f9eb;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #28a745;
            text-align: center;
        }
        .alert-box h2 {
            margin: 0;
            color: #155724;
            font-size: 20px;
        }
        /* Table Styles */
        .booking-details {
            margin: 10px 0 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
            font-size: 14px;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 140px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e; /* Brand Red */
            color: #ffffff !important;
            padding: 12px 25px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 15px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <div class="alert-box">
                <h2>Neue Buchung erhalten!</h2>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Ref: {booking_reference}</p>
            </div>
            
            <!-- Section 1: Customer Info -->
            <h3>Kundeninformationen</h3>
            <table class="booking-details">
                <tr>
                    <th>Name</th>
                    <td>{customer_name}</td>
                </tr>
                <tr>
                    <th>E-Mail</th>
                    <td><a href="mailto:{customer_email}" style="color:#981b1e;">{customer_email}</a></td>
                </tr>
                <tr>
                    <th>Telefon</th>
                    <td>{customer_phone}</td>
                </tr>
            </table>

            <!-- Section 2: Booking Info -->
            <h3>Buchungsdetails</h3>
            <table class="booking-details">
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum / Zeit</th>
                    <td>{booking_date}<br>{start_time} – {end_time} ({duration})</td>
                </tr>
                <tr>
                    <th>Gesamtbetrag</th>
                    <td><strong>{total_amount}</strong></td>
                </tr>
                <tr>
                    <th>Zahlungsart</th>
                    <td>{payment_method}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{booking_status}</td>
                </tr>
            </table>
            
            <div class="btn-container">
                <a href="{booking_url}" class="btn">Buchung im Admin-Bereich öffnen</a>
            </div>
        </div>
        
        <div class="footer">
            <p>System-Benachrichtigung von {company_name}</p>
        </div>
    </div>
</body>
</html>',
  ),
  3 => 
  array (
    'template_key' => 'payment_confirmation_admin',
    'template_type' => 'admin',
    'template_name' => 'Payment Confirmation (Admin)',
    'subject' => 'Zahlung erhalten - {booking_reference}',
    'heading' => 'Zahlung bestätigt!',
    'message' => 'A payment has been successfully processed for a booking.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Zahlung erhalten</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        h3 {
            margin-top: 25px;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 2px solid #f7f7f7;
            padding-bottom: 5px;
        }
        /* Success Box - Green for Revenue */
        .success-box {
            background: #f0f9eb;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #28a745;
            text-align: center;
        }
        .success-box h2 {
            margin: 0;
            color: #155724;
            font-size: 20px;
        }
        /* Table Styles */
        .booking-details {
            margin: 10px 0 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
            font-size: 14px;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 140px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e; /* Brand Red */
            color: #ffffff !important;
            padding: 12px 25px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 15px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <div class="success-box">
                <h2>Zahlung erfolgreich erhalten!</h2>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Ref: {booking_reference}</p>
            </div>
            
            <!-- Section 1: Customer Info -->
            <h3>Kundeninformationen</h3>
            <table class="booking-details">
                <tr>
                    <th>Name</th>
                    <td>{customer_name}</td>
                </tr>
                <tr>
                    <th>E-Mail</th>
                    <td><a href="mailto:{customer_email}" style="color:#981b1e;">{customer_email}</a></td>
                </tr>
            </table>

            <!-- Section 2: Payment Details -->
            <h3>Zahlungsdetails</h3>
            <table class="booking-details">
                <tr>
                    <th>Betrag</th>
                    <td><strong>{total_amount}</strong></td>
                </tr>
                <tr>
                    <th>Zahlungsart</th>
                    <td>{payment_method}</td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
                <tr>
                    <th>Uhrzeit</th>
                    <td>{start_time} – {end_time}</td>
                </tr>
            </table>
            
            <div class="btn-container">
                <a href="{booking_url}" class="btn">Buchung im Admin-Bereich öffnen</a>
            </div>
        </div>
        
        <div class="footer">
            <p>System-Benachrichtigung von {company_name}</p>
        </div>
    </div>
</body>
</html>',
  ),
  4 => 
  array (
    'template_key' => 'booking_reminder_user',
    'template_type' => 'user',
    'template_name' => 'Booking Reminder (User)',
    'subject' => 'Erinnerung: Ihre Buchung steht bevor - {booking_reference}',
    'heading' => 'Buchungserinnerung',
    'message' => 'This is a reminder that your booking starts in 1 hour.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buchungserinnerung</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* Reminder Box - Using Brand Red for visibility */
        .reminder-box {
            background: #fdf2f2;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #981b1e;
            text-align: center;
        }
        .reminder-box h2 {
            margin: 0;
            color: #981b1e;
            font-size: 22px;
        }
        /* Table Styles - Consistent with other templates */
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 150px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e;
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            <p>wir freuen uns auf Ihren Besuch! Dies ist eine freundliche Erinnerung an Ihre bevorstehende Buchung.</p>

            <div class="reminder-box">
                <h2>Buchungserinnerung</h2>
            </div>
            
            <table class="booking-details">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
                <tr>
                    <th>Uhrzeit</th>
                    <td>{start_time} – {end_time}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{booking_status}</td>
                </tr>
            </table>

            <div class="btn-container">
                <a href="{booking_url}" class="btn">Buchung & Anfahrt ansehen</a>
            </div>

            <p style="margin-top: 30px;">
                <strong>Wichtiger Hinweis:</strong><br>
                Bitte erscheinen Sie pünktlich. Der Zugang zum Raum ist nur für den gebuchten Zeitraum gültig.
            </p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  5 => 
  array (
    'template_key' => 'booking_cancelled_user',
    'template_type' => 'user',
    'template_name' => 'Booking Cancelled (User)',
    'subject' => 'Booking Cancelled - {booking_reference}',
    'heading' => 'Booking Cancelled',
    'message' => 'Your booking has been cancelled.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ihre Buchung wurde storniert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 150px;
            font-weight: 600;
        }
        .cancelled-box {
            background: #fdf2f2;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #981b1e; /* Your Theme\'s Dark Red */
            text-align: center;
        }
        .cancelled-box h2 {
            margin: 0;
            color: #981b1e;
            font-size: 22px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            <p>wir bestätigen hiermit die Stornierung Ihrer Buchung.</p>

            <div class="cancelled-box">
                <h2>Buchung storniert</h2>
            </div>
            
            <p>Nachfolgend finden Sie die Details der stornierten Reservierung:</p>
            
            <table class="booking-details" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
                <tr>
                    <th>Uhrzeit</th>
                    <td>{start_time} – {end_time}</td>
                </tr>
            </table>

            <p style="margin-top: 30px;">Bei Fragen zu dieser Stornierung stehen wir Ihnen gerne zur Verfügung.</p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  6 => 
  array (
    'template_key' => 'booking_modified_user',
    'template_type' => 'user',
    'template_name' => 'Booking Modified (User)',
    'subject' => 'Ihre Buchung wurde geändert - {booking_reference}',
    'heading' => 'Buchungsänderung bestätigt',
    'message' => 'Your booking has been modified. Please review the updated details:',
    'html_content' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Buchung geändert</title><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333333;margin:0;padding:0;background-color:#f7f7f7;} .container{max-width:600px;margin:20px auto;background:#ffffff;padding:30px;border:1px solid #e5e5e5;} .header{background-color:#ffffff;padding:20px 0;text-align:center;border-bottom:1px solid #e5e5e5;} .header img{max-width:250px;height:auto;} .content{padding:30px 0 10px 0;} .content p{margin:0 0 15px 0;} .booking-details{margin:25px 0;border:1px solid #e5e5e5;width:100%;border-collapse:collapse;} .booking-details th,.booking-details td{padding:12px 15px;text-align:left;border-bottom:1px solid #e5e5e5;vertical-align:top;} .booking-details th{background-color:#f7f7f7;width:140px;font-weight:600;color:#555;} .update-box{background:#eaf4fc;padding:20px;margin:0 0 25px 0;border-left:4px solid #2c8bd6;text-align:center;} .update-box h2{margin:0;color:#1f6fb0;font-size:20px;} .amount-row td{background-color:#fffcf5;color:#981b1e;font-weight:bold;font-size:16px;} .btn-container{text-align:center;margin:35px 0;} .btn{background-color:#981b1e;color:#ffffff !important;padding:15px 30px;text-decoration:none;font-weight:bold;border-radius:4px;display:inline-block;font-size:16px;} .footer{margin-top:30px;padding-top:20px;border-top:1px solid #e5e5e5;font-size:12px;color:#666666;text-align:center;}</style></head><body><div class="container"><div class="header">{company_logo_html}</div><div class="content"><p>Liebe/r {customer_name},</p><p>Ihre Buchung wurde aktualisiert. Nachfolgend finden Sie die geänderten Details Ihrer Reservierung.</p><div class="update-box"><h2>Buchung geändert</h2></div><table class="booking-details"><tr><th>Buchungsnummer</th><td><strong>{booking_reference}</strong></td></tr><tr><th>Raum</th><td>{room_name}</td></tr><tr><th>Datum / Zeit</th><td>{booking_date}<br><small>{start_time} – {end_time}</small></td></tr><tr><th>Dauer</th><td>{duration}</td></tr><tr><th>Zahlungsart</th><td>{payment_method}</td></tr><tr><th>Status</th><td>{booking_status}</td></tr><tr class="amount-row"><th>Gesamtbetrag</th><td>{total_amount}</td></tr></table><div class="btn-container"><a href="{booking_url}" class="btn">Aktualisierte Buchung ansehen</a></div><p style="margin-top:30px;">Bei Fragen zu diesen Änderungen stehen wir Ihnen gerne zur Verfügung.</p></div><div class="footer"><p><strong>{company_name}</strong><br>{company_phone} | {company_email}</p></div></div></body></html>',
  ),
  7 => 
  array (
    'template_key' => 'otp_verification_user',
    'template_type' => 'user',
    'template_name' => 'OTP Verification (User)',
    'subject' => 'Ihr Verifizierungscode - {otp_code}',
    'heading' => 'E-Mail Verifizierung',
    'message' => 'Please use the following code to verify your email address:',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ihr Verifizierungscode</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* OTP Highlight Box */
        .otp-box {
            background: #f9f9f9;
            padding: 30px;
            margin: 25px 0;
            text-align: center;
            border: 1px dashed #cccccc;
            border-radius: 4px;
        }
        .otp-label {
            margin: 0;
            font-size: 14px;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-number {
            display: block;
            font-size: 36px;
            font-weight: bold;
            color: #981b1e; /* Brand Red */
            letter-spacing: 8px;
            margin: 10px 0;
            font-family: \'Courier New\', Courier, monospace; /* Monospace for readability */
        }
        .otp-expiry {
            margin: 0;
            font-size: 12px;
            color: #888888;
        }
        /* Security Warning Box */
        .security-box {
            background: #fdf2f2;
            padding: 15px;
            margin: 25px 0;
            border-left: 4px solid #981b1e;
            font-size: 13px;
            color: #333;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            
            <p>um Ihre Buchung oder Anmeldung abzuschließen, bestätigen Sie bitte Ihre E-Mail-Adresse mit dem folgenden Code:</p>
            
            <div class="otp-box">
                <p class="otp-label">Ihr Verifizierungscode</p>
                <span class="otp-number">{otp_code}</span>
                <p class="otp-expiry">Dieser Code ist für 15 Minuten gültig.</p>
            </div>
            
            <div class="security-box">
                <strong>⚠️ Sicherheits-Hinweis:</strong><br>
                Bitte geben Sie diesen Code nicht an Dritte weiter. Unser Team wird Sie niemals telefonisch oder per E-Mail nach diesem Code fragen.
            </div>
            
            <p style="font-size: 13px; color: #666;">
                Wenn Sie diesen Code nicht angefordert haben, können Sie diese E-Mail ignorieren.
            </p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  8 => 
  array (
    'template_key' => 'online_payment_pending',
    'template_type' => 'user',
    'template_name' => 'Online Payment Pending',
    'subject' => 'PayPal-Zahlung ausstehend - Buchung {booking_reference}',
    'heading' => 'PayPal-Zahlung ausstehend',
    'message' => 'Die Zahlung über Paypal ist noch ausstehend. Sollte diese nicht innerhalb der nächsten 15min durchgeführt werden, wird Ihre Buchung auf Grund fehlender Zahlung storniert.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PayPal-Zahlung ausstehend</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* Action Box - Red for Urgency/Warning */
        .action-box {
            background: #fdf2f2;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #981b1e;
            text-align: center;
        }
        .action-box h2 {
            margin: 0;
            color: #981b1e;
            font-size: 20px;
        }
        .action-box p {
            margin: 10px 0 0 0;
            font-size: 14px;
            font-weight: bold;
        }
        /* Table Styles */
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 150px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e; /* Brand Red */
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            
            <p>vielen Dank für Ihre Buchung. Wir haben jedoch noch keine Bestätigung von PayPal erhalten.</p>

            <div class="action-box">
                <h2>Zahlung ausstehend</h2>
                <p>⚠️ Wichtiger Hinweis:<br>
                Bitte schließen Sie die Zahlung innerhalb der nächsten 15 Minuten ab, da Ihre Reservierung sonst automatisch storniert wird.</p>
            </div>
            
            <p>Bitte führen Sie die Zahlung so schnell wie möglich durch, um Ihre Buchung zu sichern.</p>
            
            <table class="booking-details">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
                <tr>
                    <th>Uhrzeit</th>
                    <td>{start_time} – {end_time}</td>
                </tr>
                <tr>
                    <th>Betrag</th>
                    <td>{total_amount} €</td>
                </tr>
            </table>

            <div class="btn-container">
                <a href="{payment_link}" class="btn">Jetzt mit PayPal bezahlen</a>
            </div>
            
            <p style="font-size: 13px; color: #666; margin-top: 20px;">
                Sollten Sie die Zahlung gerade eben durchgeführt haben, können Sie diese Nachricht ignorieren.
            </p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  9 => 
  array (
    'template_key' => 'payment_timeout_cancellation',
    'template_type' => 'user',
    'template_name' => 'Payment Timeout Cancellation',
    'subject' => 'Ihre Buchung wurde storniert – fehlende PayPal-Zahlung',
    'heading' => 'Buchung storniert',
    'message' => 'Sehr geehrte*r [Name], leider konnten wir innerhalb der vorgesehenen Frist keine abgeschlossene PayPal-Zahlung zu Ihrer Buchung feststellen. Da unser System das Zimmer nur für 15 Minuten reserviert, wurde Ihre Buchung automatisch storniert, da die Zahlung nicht rechtzeitig durchgeführt wurde. Sollten Sie weiterhin Interesse an einer Buchung haben, können Sie gerne eine neue Reservierung über unser System vornehmen. Bei Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. Mit freundlichen Grüßen',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buchung storniert - Zahlung nicht erfolgt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* Cancelled Box - Red for Stornierung */
        .cancelled-box {
            background: #fdf2f2;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #981b1e; /* Brand Red */
            text-align: center;
        }
        .cancelled-box h2 {
            margin: 0;
            color: #981b1e;
            font-size: 20px;
        }
        .cancelled-box p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        /* Table Styles */
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 150px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e; /* Brand Red */
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            
            <p>leider konnten wir innerhalb der vorgesehenen Reservierungszeit (15 Minuten) keine Zahlung feststellen.</p>

            <div class="cancelled-box">
                <h2>Buchung storniert</h2>
                <p>Zahlung nicht rechtzeitig erhalten</p>
            </div>
            
            <p>Daher wurde Ihre Reservierung automatisch vom System freigegeben. Falls Sie den Raum dennoch buchen möchten, bitten wir Sie, eine neue Reservierung vorzunehmen.</p>
            
            <p><strong>Details der stornierten Anfrage:</strong></p>
            
            <table class="booking-details">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
                <tr>
                    <th>Uhrzeit</th>
                    <td>{start_time} – {end_time}</td>
                </tr>
                <tr>
                    <th>Betrag</th>
                    <td>{total_amount}</td>
                </tr>
            </table>

            <div class="btn-container">
                <a href="{booking_url}" class="btn">Neue Buchung vornehmen</a>
            </div>
            
            <p style="margin-top: 30px; font-size: 13px;">
                Falls Sie die Zahlung getätigt haben, diese aber nicht erkannt wurde, kontaktieren Sie uns bitte umgehend.
            </p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  10 => 
  array (
    'template_key' => 'paypal_payment_required_user',
    'template_type' => 'user',
    'template_name' => 'PayPal Payment Required',
    'subject' => 'PayPal-Zahlung erforderlich - Buchung {booking_reference}',
    'heading' => 'PayPal-Zahlung erforderlich',
    'message' => 'Ihre Buchung wurde auf PayPal-Zahlung umgestellt. Bitte führen Sie die Zahlung über den unten stehenden Link durch.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PayPal-Zahlung erforderlich</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* Action Box - Red for Action Required */
        .action-box {
            background: #fdf2f2;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #981b1e; /* Brand Red */
            text-align: center;
        }
        .action-box h2 {
            margin: 0;
            color: #981b1e;
            font-size: 20px;
        }
        .action-box p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        /* Table Styles */
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 150px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e; /* Brand Red */
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            
            <p>Ihre Buchung wurde auf die Zahlungsart PayPal umgestellt. Um die Reservierung abzuschließen, ist nun Ihre Zahlung erforderlich.</p>

            <div class="action-box">
                <h2>PayPal-Zahlung erforderlich</h2>
                <p>Bitte begleichen Sie den offenen Betrag.</p>
            </div>
            
            <table class="booking-details">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
                <tr>
                    <th>Uhrzeit</th>
                    <td>{start_time} – {end_time}</td>
                </tr>
                <tr>
                    <th>Dauer</th>
                    <td>{duration}</td>
                </tr>
                <tr>
                    <th>Betrag</th>
                    <td><strong>{total_amount}</strong></td>
                </tr>
                <tr>
                    <th>Zahlungsart</th>
                    <td>{payment_method}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{booking_status}</td>
                </tr>
            </table>

            <div class="btn-container">
                <a href="{payment_link}" class="btn">Jetzt mit PayPal bezahlen</a>
            </div>
            
            <p style="margin-top: 30px; font-size: 13px; color: #666;">
                Hinweis: Bitte führen Sie die Zahlung zeitnah durch, damit wir Ihre Buchung verbindlich bestätigen können.
            </p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  11 => 
  array (
    'template_key' => 'invoice_regenerated_user',
    'template_type' => 'user',
    'template_name' => 'Updated Invoice (User)',
    'subject' => 'Aktualisierte Rechnung - {booking_reference}',
    'heading' => 'Aktualisierte Rechnung',
    'message' => 'Your invoice has been updated. Please find the updated invoice attached to this email.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Aktualisierte Rechnung</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* Update Box - Blue for Information/Change */
        .update-box {
            background: #eaf4fc;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #2c8bd6; /* Blue for Updates */
            text-align: center;
        }
        .update-box h2 {
            margin: 0;
            color: #1d6fa5;
            font-size: 22px;
        }
        /* Table Styles */
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 160px;
            font-weight: 600;
            color: #555;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e; /* Brand Red */
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_first_name},</p>
            
            <p>anbei erhalten Sie Informationen zu Ihrer aktualisierten Rechnung.</p>

            <div class="update-box">
                <h2>Aktualisierte Rechnung</h2>
            </div>
            
            <p>Die Details Ihrer Rechnung haben sich geändert. Nachfolgend finden Sie die aktuelle Übersicht:</p>
            
            <table class="booking-details">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Gesamtbetrag</th>
                    <td><strong>{total_amount}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum</th>
                    <td>{booking_date}</td>
                </tr>
            </table>

            <div class="btn-container">
                <a href="{booking_url}" class="btn">Buchung & Rechnung ansehen</a>
            </div>
            
            <p style="margin-top: 30px; font-size: 13px;">
                Die aktualisierte Rechnung steht in Ihrem Kundenkonto zum Download bereit.
            </p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  12 => 
  array (
    'template_key' => 'additional_payment_required_user',
    'template_type' => 'user',
    'template_name' => 'Additional Payment Required',
    'subject' => 'Zusätzliche Zahlung erforderlich - Buchung {booking_reference}',
    'heading' => 'Zusätzliche Zahlung erforderlich',
    'message' => 'Ihre Buchung wurde um zusätzliche Dienstleistungen erweitert. Bitte führen Sie die Zahlung für die zusätzlichen Leistungen über den unten stehenden Link durch.',
    'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Zusätzliche Zahlung erforderlich</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e5e5;
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        .header img {
            max-width: 250px;
            height: auto;
        }
        .content {
            padding: 30px 0 10px 0;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        /* Reusing your table style */
        .booking-details {
            margin: 25px 0;
            border: 1px solid #e5e5e5;
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details th, .booking-details td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        .booking-details th {
            background-color: #f7f7f7;
            width: 140px;
            font-weight: 600;
            color: #555;
        }
        /* Alert Box - Using your Theme Red for Action Required */
        .action-box {
            background: #fdf2f2;
            padding: 20px;
            margin: 0 0 25px 0;
            border-left: 4px solid #981b1e;
            text-align: center;
        }
        .action-box h2 {
            margin: 0;
            color: #981b1e;
            font-size: 20px;
        }
        .action-box p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        /* Amount Highlight */
        .amount-row td {
            background-color: #fffcf5;
            color: #981b1e;
            font-weight: bold;
            font-size: 16px;
        }
        /* Button Style */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }
        .btn {
            background-color: #981b1e;
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #7a1618;
        }
        .services-list {
            background-color: #f9f9f9;
            padding: 15px;
            border-left: 3px solid #ccc;
            margin-top: 5px;
            font-size: 14px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {company_logo_html}
        </div>
        
        <div class="content">
            <p>Liebe/r {customer_name},</p>
            
            <p>vielen Dank für Ihre Buchung. Wir bestätigen hiermit, dass Ihre Buchung um zusätzliche Dienstleistungen erweitert wurde.</p>

            <div class="action-box">
                <h2>Zusätzliche Zahlung erforderlich</h2>
                <p>Bitte gleichen Sie den offenen Betrag zeitnah aus.</p>
            </div>
            
            <table class="booking-details">
                <tr>
                    <th>Buchungsnummer</th>
                    <td><strong>{booking_reference}</strong></td>
                </tr>
                <tr>
                    <th>Raum</th>
                    <td>{room_name}</td>
                </tr>
                <tr>
                    <th>Datum / Zeit</th>
                    <td>{booking_date}<br><small>{start_time} – {end_time}</small></td>
                </tr>
                <!-- New Section for Added Services -->
                <tr>
                    <th>Zusatzleistungen</th>
                    <td>
                        Die folgenden Leistungen wurden hinzugefügt:
                        <div class="services-list">
                            {added_services}
                        </div>
                    </td>
                </tr>
                <!-- Total Amount Row -->
                <tr class="amount-row">
                    <th>Offener Betrag</th>
                    <td>{additional_amount}</td>
                </tr>
            </table>

            <p>Damit wir diese Änderungen final für Sie reservieren können, bitten wir Sie, die Zahlung über den folgenden Button vorzunehmen:</p>

            <div class="btn-container">
                <a href="{payment_link}" class="btn">Jetzt bezahlen</a>
            </div>
            
            <p style="font-size: 13px; color: #666;">
                <em>Hinweis: Sollten Sie die Zahlung bereits getätigt haben, können Sie diese E-Mail ignorieren.</em>
            </p>

            <p style="margin-top: 30px;">Bei Fragen zur Zahlung stehen wir Ihnen gerne zur Verfügung.</p>
        </div>
        
        <div class="footer">
            <p>
                <strong>{company_name}</strong><br>
                {company_phone} | {company_email}
            </p>
        </div>
    </div>
</body>
</html>',
  ),
  13 => 
  array (
    'template_key' => 'daily_summary_admin',
    'template_type' => 'admin',
    'template_name' => 'Daily Summary (Admin)',
    'subject' => 'Termine am {summary_date}: {total_bookings}, {total_revenue} - {company_name}',
    'heading' => 'Tageszusammenfassung',
    'message' => 'Die Termine des Tages, Zahlungseingang und offene Beträge.',
    'html_content' => '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Tageszusammenfassung</title><style>@media only screen and (max-width:620px){.px{padding-left:18px!important;padding-right:18px!important;}.stack{display:block!important;width:100%!important;padding:0 0 10px 0!important;}}</style></head><body style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;margin:0;padding:0;background:#eef0f4;color:#1f2328;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#eef0f4" style="background:#eef0f4;"><tr><td align="center" style="padding:24px 12px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;background:#ffffff;border-radius:10px;overflow:hidden;"><tr><td bgcolor="#1f2328" style="background:#1f2328;padding:24px 32px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td valign="middle" style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;"><div style="color:#9aa3ad;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;">Tageszusammenfassung</div><div style="color:#ffffff;font-size:22px;font-weight:700;padding-top:3px;">{summary_date}</div></td><td valign="middle" align="right">{company_logo_html}</td></tr></table></td></tr><tr><td class="px" style="padding:26px 32px 0 32px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;border-spacing:10px 0;"><tr><td class="stack" width="50%" valign="top" style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;padding:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;"><tr><td bgcolor="#ffffff" style="background:#ffffff;border:1px solid #e3e6ea;border-top:3px solid #2a78d6;border-radius:6px;padding:15px 16px;"><div style="font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;font-weight:700;line-height:1.35;">Termine</div><div style="font-size:26px;line-height:1.2;font-weight:700;color:#1f2328;padding-top:6px;">{total_bookings}</div><div style="font-size:12px;color:#6b7280;padding-top:5px;line-height:1.4;">{confirmed_bookings} bestätigt · {hours_booked} Std.</div></td></tr></table></td><td class="stack" width="50%" valign="top" style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;padding:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;"><tr><td bgcolor="#ffffff" style="background:#ffffff;border:1px solid #e3e6ea;border-top:3px solid #0ca30c;border-radius:6px;padding:15px 16px;"><div style="font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;font-weight:700;line-height:1.35;">Zahlungseingang</div><div style="font-size:26px;line-height:1.2;font-weight:700;color:#1f2328;padding-top:6px;">{payments_received}</div><div style="font-size:12px;color:#6b7280;padding-top:5px;line-height:1.4;">für diese Termine, inkl. Stornogebühren</div></td></tr></table></td></tr><tr><td colspan="2" height="10" style="height:10px;line-height:10px;font-size:0;">&nbsp;</td></tr><tr><td class="stack" width="50%" valign="top" style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;padding:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;"><tr><td bgcolor="#ffffff" style="background:#ffffff;border:1px solid #e3e6ea;border-top:3px solid #d03b3b;border-radius:6px;padding:15px 16px;"><div style="font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;font-weight:700;line-height:1.35;">Stornogebühren</div><div style="font-size:26px;line-height:1.2;font-weight:700;color:#1f2328;padding-top:6px;">{pending_cancellation_fees}</div><div style="font-size:12px;color:#6b7280;padding-top:5px;line-height:1.4;">für diese Termine, noch offen</div></td></tr></table></td><td class="stack" width="50%" valign="top" style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;padding:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;"><tr><td bgcolor="#ffffff" style="background:#ffffff;border:1px solid #e3e6ea;border-top:3px solid #fab219;border-radius:6px;padding:15px 16px;"><div style="font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;font-weight:700;line-height:1.35;">Offen</div><div style="font-size:26px;line-height:1.2;font-weight:700;color:#1f2328;padding-top:6px;">{outstanding_bookings}</div><div style="font-size:12px;color:#6b7280;padding-top:5px;line-height:1.4;">für diese Termine, ohne Stornogebühren</div></td></tr></table></td></tr></table></td></tr><tr><td class="px" style="padding:30px 32px 0 32px;"><div style="font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#6b7280;font-weight:700;padding-bottom:12px;">Terminübersicht &ndash; {summary_date}</div>{day_narrative}<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin-top:6px;">{unpaid_booking_rows}</table></td></tr><tr><td class="px" style="padding:30px 32px 0 32px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;border-spacing:14px 0;"><tr><td class="stack" width="50%" valign="top"><div style="font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#6b7280;font-weight:700;padding-bottom:12px;">Buchungsstatus</div><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:10px 0;border-bottom:1px solid #eceef1;font-size:14px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#0ca30c;margin-right:8px;"></span>Bestätigt</td><td align="right" style="padding:10px 0;border-bottom:1px solid #eceef1;font-size:14px;font-weight:600;">{confirmed_bookings}</td></tr><tr><td style="padding:10px 0;border-bottom:1px solid #eceef1;font-size:14px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fab219;margin-right:8px;"></span>Ausstehend</td><td align="right" style="padding:10px 0;border-bottom:1px solid #eceef1;font-size:14px;font-weight:600;">{pending_bookings}</td></tr><tr><td style="padding:10px 0;font-size:14px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#d03b3b;margin-right:8px;"></span>Storniert</td><td align="right" style="padding:10px 0;font-size:14px;font-weight:600;">{cancelled_bookings}</td></tr></table></td><td class="stack" width="50%" valign="top"><div style="font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#6b7280;font-weight:700;padding-bottom:12px;">Zahlungsstatus</div><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">{payment_status_rows}</table></td></tr></table></td></tr><tr><td class="px" style="padding:28px 32px 30px 32px;"><div style="border-top:1px solid #eceef1;padding-top:18px;font-size:12px;line-height:1.7;color:#6b7280;text-align:center;"><strong style="color:#1f2328;">{company_name}</strong><br>{company_phone} &nbsp;|&nbsp; {company_email}<br><span style="font-size:11px;">Automatisch erstellt – keine Antwort erforderlich.</span></div></td></tr></table></td></tr></table></body></html>',
  ),
  14 => 
  array (
    'template_key' => 'arrival_reminder_admin',
    'template_type' => 'admin',
    'template_name' => 'Arrival Reminder (Admin)',
    'subject' => 'In 15 Minuten: {customer_name} – {room_name}',
    'heading' => 'Ankunft in Kürze',
    'message' => 'Ein Kunde trifft in 15 Minuten ein.',
    'html_content' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Ankunft in Kürze</title><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333333;margin:0;padding:0;background-color:#f7f7f7;} .container{max-width:600px;margin:20px auto;background:#ffffff;padding:30px;border:1px solid #e5e5e5;} .header{background-color:#ffffff;padding:20px 0;text-align:center;border-bottom:1px solid #e5e5e5;} .header img{max-width:250px;height:auto;} .content{padding:30px 0 10px 0;} .content p{margin:0 0 15px 0;} .booking-details{margin:25px 0;border:1px solid #e5e5e5;width:100%;border-collapse:collapse;} .booking-details th,.booking-details td{padding:12px 15px;text-align:left;border-bottom:1px solid #e5e5e5;vertical-align:top;} .booking-details th{background-color:#f7f7f7;width:150px;font-weight:600;color:#555;} .alert-box{background:#fff8e6;padding:20px;margin:0 0 25px 0;border-left:4px solid #d98b00;text-align:center;} .alert-box h2{margin:0;color:#8a5a00;font-size:20px;} .amount-row td{background-color:#fffcf5;color:#981b1e;font-weight:bold;font-size:16px;} .footer{margin-top:30px;padding-top:20px;border-top:1px solid #e5e5e5;font-size:12px;color:#666666;text-align:center;}</style></head><body><div class="container"><div class="header">{company_logo_html}</div><div class="content"><div class="alert-box"><h2>In 15 Minuten: {customer_name} – {room_name}</h2></div><p>In 15 Minuten trifft <strong>{customer_name}</strong> für <strong>{room_name}</strong> ein.</p><table class="booking-details"><tr><th>Kunde</th><td><strong>{customer_name}</strong></td></tr><tr><th>Raum</th><td>{room_name}</td></tr><tr><th>Datum / Zeit</th><td>{booking_date}<br><small>{start_time} – {end_time}</small></td></tr><tr><th>Dauer</th><td>{duration}</td></tr><tr><th>Buchungsnummer</th><td>{booking_reference}</td></tr><tr><th>Telefon</th><td>{customer_phone}</td></tr><tr><th>E-Mail</th><td>{customer_email}</td></tr><tr><th>Zahlungsart</th><td>{payment_method}</td></tr><tr><th>Zahlungsstatus</th><td>{payment_status}</td></tr><tr class="amount-row"><th>Gesamtbetrag</th><td>{total_amount}</td></tr></table></div><div class="footer"><p><strong>{company_name}</strong><br>{company_phone} | {company_email}</p></div></div></body></html>',
  ),
  15 => 
  array (
    'template_key' => 'booking_cancelled_fee_user',
    'template_type' => 'user',
    'template_name' => 'Booking Cancelled with Fee (Customer)',
    'subject' => 'Offene Stornogebühr {cancellation_fee} - Buchung {booking_reference}',
    'heading' => 'Stornogebühr offen',
    'message' => 'Ihre Buchung wurde storniert. Die Stornogebühr ist noch offen.',
    'html_content' => '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Stornogeb&uuml;hr</title><style>body{margin:0;padding:0;background:#f0f1f3;-webkit-font-smoothing:antialiased;} .wrap{max-width:640px;margin:0 auto;background:#ffffff;} .px{padding-left:32px;padding-right:32px;} h3{margin:0 0 10px 0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;} .details{border-collapse:collapse;width:100%;} .details th{text-align:left;font-weight:600;color:#6b7280;font-size:14px;padding:10px 16px 10px 0;border-bottom:1px solid #e5e7eb;width:150px;vertical-align:top;} .details td{font-size:14px;color:#1f2328;padding:10px 0;border-bottom:1px solid #e5e7eb;} .details tr:last-child th,.details tr:last-child td{border-bottom:0;} .bank{border-collapse:collapse;width:100%;} .bank td{padding:6px 0;font-size:15px;color:#1f2328;vertical-align:top;} .bank td.k{color:#6b7280;font-size:13px;padding-right:16px;white-space:nowrap;width:140px;} .bank td.v{font-weight:700;font-family:Consolas,Menlo,monospace;letter-spacing:.02em;} @media only screen and (max-width:620px){ .px{padding-left:20px!important;padding-right:20px!important;} .bank td{display:block!important;width:auto!important;padding:2px 0!important;} .bank td.k{padding-top:10px!important;} }</style></head><body style="font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;background:#f0f1f3;color:#1f2328;"><div class="wrap"><div class="px" style="padding-top:28px;padding-bottom:22px;text-align:center;border-bottom:1px solid #e5e7eb;">{company_logo_html}</div><div class="px" style="padding-top:30px;"><p style="margin:0 0 14px 0;font-size:15px;">Liebe/r {customer_name},</p><p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;">Ihre Buchung <strong>{booking_reference}</strong> wurde storniert. F&uuml;r die Stornierung berechnen wir eine <strong>Stornogeb&uuml;hr</strong>, die noch offen ist.</p></div><div class="px" style="padding-top:22px;"><div style="border:2px solid #981b1e;border-radius:4px;overflow:hidden;"><div style="background:#981b1e;padding:16px 22px;"><div style="color:#ffd9d8;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;">Zu zahlen</div><div style="color:#ffffff;font-size:34px;line-height:1.15;font-weight:700;padding-top:2px;">{cancellation_fee}</div><div style="color:#ffd9d8;font-size:14px;padding-top:6px;">F&auml;llig bis <strong style="color:#ffffff;">{cancellation_fee_due_date}</strong></div></div><div style="padding:20px 22px;background:#fdf6f6;"><div style="font-size:14px;font-weight:700;padding-bottom:12px;">Bitte &uuml;berweisen Sie den Betrag auf dieses Konto:</div><table class="bank"><tr><td class="k">Kontoinhaber</td><td class="v">{bank_holder}</td></tr><tr><td class="k">IBAN</td><td class="v">{bank_iban}</td></tr><tr><td class="k">BIC</td><td class="v">{bank_bic}</td></tr><tr><td class="k">Verwendungszweck</td><td class="v">{booking_reference}</td></tr></table><div style="margin-top:14px;padding-top:12px;border-top:1px solid #f0cfcd;font-size:13px;line-height:1.6;color:#1f2328;">Bitte geben Sie <strong>{booking_reference}</strong> als Verwendungszweck an, damit wir Ihre Zahlung zuordnen k&ouml;nnen.</div><div style="margin-top:10px;font-size:14px;font-weight:700;color:#981b1e;">PayPal wird f&uuml;r die Stornogeb&uuml;hr nicht akzeptiert.</div></div></div><p style="margin:14px 0 0 0;font-size:13px;color:#6b7280;">Die Rechnung &uuml;ber die Stornogeb&uuml;hr finden Sie als PDF im Anhang dieser E-Mail.</p></div><div class="px" style="padding-top:34px;"><h3>Stornierte Buchung</h3><table class="details"><tr><th>Buchungsnummer</th><td><strong>{booking_reference}</strong></td></tr><tr><th>Raum</th><td>{room_name}</td></tr><tr><th>Datum</th><td>{booking_date}</td></tr><tr><th>Uhrzeit</th><td>{start_time} &ndash; {end_time}</td></tr></table></div><div class="px" style="padding-top:30px;padding-bottom:30px;"><p style="margin:0 0 18px 0;font-size:14px;line-height:1.6;color:#6b7280;">Bei Fragen zu dieser Stornierung oder zur Geb&uuml;hr stehen wir Ihnen gerne zur Verf&uuml;gung.</p><div style="border-top:1px solid #e5e7eb;padding-top:18px;font-size:12px;line-height:1.7;color:#6b7280;text-align:center;"><strong style="color:#1f2328;">{company_name}</strong><br>{company_phone} &nbsp;|&nbsp; {company_email}</div></div></div></body></html>',
  ),
);
