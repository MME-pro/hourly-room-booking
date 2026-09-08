# Changelog

All notable changes to the Hourly Room Booking System plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.10.5] - 2026-09-09

### Changed
- **The written summary now accounts for the whole day.** It opens with what came in and how it was meant to be paid ("On 09.11.2026, 4 bookings were taken: 2 on site and 2 PayPal"), says what was cancelled and what is left ("Cancelled: 3 (2 on site and 1 PayPal). Still standing: 1"), and then lists the finances as labelled figures rather than another sentence: revenue, money received per method, and anything still pending per method. Cancelled bookings are counted in the sentences but stay out of the list of people to collect from — a cancelled booking owes the fee, not the room.
- **The revenue line says what it counts.** It reads "Revenue from bookings that stand" rather than "Total revenue", because money received can legitimately exceed it: a cancelled booking that was paid for is not refunded, so the money is real income while the booking is no longer counted. Two figures that look contradictory need the label to explain them.

### Fixed
- The opening sentence said "1 bookings were taken"; it goes through `_n()` now, with both German forms in the catalogue. The sentence about cancellations was reshaped so no verb has to agree with a number that changes daily.
- The written summary and the empty booking list both announced that everything was paid for, one after the other. The list stays quiet when the sentences have already said it.
## [1.10.4] - 2026-09-09

### Added
- **The daily summary ends with the day in words, and names who still owes money.** Below the figures it now reads "Am 09.09.2026 sind 6 Buchungen eingegangen. Davon haben 3 bereits bezahlt. 3 zahlen vor Ort, insgesamt 295,00 €:" followed by one line per unpaid booking — customer, room, time, reference and amount. Customers who have already paid are counted but not listed: the list is there to be worked through at the desk. Cancelled and no-show bookings are left out of both. New placeholders: `{day_narrative}` and `{unpaid_booking_rows}`.

### Changed
- **Card titles are a single word each** — Buchungen, Zahlungseingang, Stornogebühren, Offen — with the explanatory line kept underneath so the shorter titles do not lose their meaning.
- **The per-payment-method table and the per-room table were removed** from the bundled summary. `{payment_method_rows}` and `{rooms_rows}` still work for any template that kept them.
## [1.10.3] - 2026-09-08

### Changed
- **The daily summary opens with four figures.** New bookings, money received today (including any cancellation fee that was paid), cancellation fees still outstanding, and booking money still outstanding. They run two per row on a desktop and stack on a phone. The "on-site against PayPal" bar and the separate payments panel are gone — the money they carried is in the cards, and the per-method table below still shows the split.

### Added
- **Outstanding money is reported as two figures, not one.** A room somebody still has to pay for and a penalty on a booking that no longer exists are chased differently, so the summary keeps them apart: `{outstanding_bookings}` excludes cancellation fees, `{pending_cancellation_fees}` counts only them. Fees are counted on the day the fee was raised rather than the day the booking was taken — a fee charged today on last month's booking is today's outstanding money. `{outstanding}` keeps its old meaning (both together) so an edited template still works.

## [1.10.2] - 2026-09-08

### Changed
- **The daily summary is a proper visual summary.** Two headline cards open it — bookings taken and revenue — followed by a stacked bar splitting the day's revenue between on-site and PayPal with a legend giving each channel's amount and share, a payment-method table with a bar per row, booking and payment status side by side with coloured markers, a highlighted payments panel, and room usage with a bar per room. Every chart is built from table cells with `bgcolor`, so there is no script and no image to be blocked; it renders in Outlook and stacks to one column on a phone. Measured at 700px, 430px and 375px: nothing overflows and the page never scrolls sideways.
- **Chart colours are assigned by entity and validated.** Each payment method keeps its own hue whether or not the others appear, so a quiet day cannot repaint the table; a method the plugin does not recognise is deliberately neutral rather than given a generated colour. The four hues pass the colourblind-separation, lightness and chroma checks against a white surface (worst adjacent CVD ΔE 9.1). Status colours come from a reserved set that shares nothing with the method hues, and always sit beside the written status.
- **The cancellation fee email leads with the money.** The amount, the deadline, the bank details and the "PayPal is not accepted" notice now come before the record of what was cancelled, instead of below it — a customer should not have to scroll past a booking summary to find out they owe something. The wording says plainly that the fee is outstanding, the subject line reads *Offene Stornogebühr …*, and a line asks the customer to quote the booking reference so the payment can be matched.

### Added
- **The fee email states the payment deadline.** The invoice already printed one, fourteen days out; the mail said nothing. Both now read it from `HRB_Invoice_Generator::cancellation_fee_due_date()`, so the date in the message is always the date on the attached PDF. New placeholder: `{cancellation_fee_due_date}`.

### Fixed
- `HRB_Daily_Summary::share_of()` returned an integer when a value clamped to 0 or 100, despite being documented as returning a float — `min()`/`max()` hand back whichever argument won.
## [1.10.1] - 2026-09-08

### Fixed
- **Cancelling a booking by changing its status sent the "booking modified" email.** The Cancel action and the edit form take different routes through the code: `cancel_booking()` sent the cancellation letter, while setting the status to `cancelled` on the edit form fell through to the generic "your booking has been modified" mail. Worse, on a booking that had just been charged the fee, that mail carried no fee, no bank details and no invoice — the customer was billed and never told where to pay. Both routes now send the same letter, chosen by `HRB_Booking_Manager::notification_event_for_change()`. Only the transition into `cancelled` counts, so editing an already-cancelled booking is still a modification, and every other status move (completed, no-show, confirming, reinstating) is unchanged.
- **The daily summary send time was shown in 12-hour form.** The field was an `<input type="time">`, which the browser draws in its own locale: an en-US browser showed AM/PM whatever the site language was, and nothing on the page could change it. It is a list of 24-hour times now. A stored time that is not on the half-hour grid is kept and sorted into place rather than being quietly rounded away.

### Changed
- **The daily summary email was rewritten.** It opens with the two figures that matter — bookings taken and revenue — each with the on-site/PayPal split underneath, then the breakdown by payment method, booking status, payment status, money in and out, and room usage. Labels were reworked throughout: *Buchungsumsatz* rather than "Buchungswert", *Zahlungseingang heute*, *Offene Forderungen*, *Auslastung nach Raum*. The subject now carries the day's figures, so the inbox list is useful on its own. Styling is inline rather than a `<style>` block, because Outlook and most webmail strip the document head, and the layout stacks on a phone.
- **`{payments_received}` is the amount on its own.** It used to have the transaction count glued on in brackets — "237,55 € (3)" — which cannot be laid out. The count is `{payments_count}`.
## [1.10.0] - 2026-09-08

### Fixed
- **The cancellation fee was demanded with a blank IBAN.** `HRB_Invoice_Generator::get_bank_details()` read the account holder, IBAN and BIC with `get_option($key, '')`, which returns the empty fallback on any site whose options row was never written. Those three settings only arrived in 1.8.0, so on a site installed before that — every existing site — they were empty until the settings screen happened to be saved. The mail went out asking for €15.00 and gave the customer no account to send it to. The details now come through `HRB_Settings`, which knows the declared defaults.
- **A customer who chose PayPal and never paid cancelled for free.** The fee turned on the payment *method*: only `onsite` and `cash` bookings were charged. A PayPal booking left pending was never paid for and never charged either.

### Changed
- **The cancellation fee now turns on whether the booking was paid for, not how it was going to be paid.** A settled booking is not charged — the amount already taken is kept rather than refunded, which is the penalty in itself. Everything still outstanding owes the flat fee, whether the customer meant to settle it on site or through PayPal. `HRB_Booking_Manager::charges_cancellation_fee()` is the rule.
- **Cancellations are two separate emails instead of one with a block that is sometimes empty.** A customer who owes nothing gets *Booking Cancelled (User)*, unchanged apart from the fee block being removed. A customer who owes the fee gets a new *Booking Cancelled with Fee (Customer)* template carrying the amount, the bank details, the reference to quote, an explicit "PayPal is not accepted for the cancellation fee", and a note that the invoice is attached. Both are editable on the Email Templates screen. The existing template is re-synced by name on update, so edits to every other template survive.
- **Bank details reach the template as separate tokens** — `{bank_holder}`, `{bank_iban}`, `{bank_bic}` alongside `{cancellation_fee}` — so the fee mail can be laid out freely instead of being stuck with one prebuilt block. `{cancellation_fee_notice_html}` still works.

### Added
- **A failed fee invoice is now logged.** The mail still goes out — the customer needs the bank details either way — but a fee demand arriving without its PDF is written to the error log with the booking reference, rather than being discovered from a complaint months later.
## [1.9.1] - 2026-09-07

### Fixed
- **Sites were told they were up to date for hours after a release went out.** The updater cached its GitHub lookup for six hours no matter what it found. WordPress refreshes its own plugin update transient far more often than that — roughly hourly while an admin is on the plugins screen — and every one of those refreshes was answered from a cache still holding the *previous* release, with GitHub returning the new one correctly the whole time. Only a manual "Check again" (which sets `force-check`) broke through, which is why the update appeared to simply never arrive. The lookup is now cached for six hours only while an update is actually pending, and for 30 minutes while the site is up to date — the state in which a new release has to be noticed.
## [1.9.0] - 2026-09-07

### Added
- **Arrival reminder to the team.** Fifteen minutes before a booking starts, the same addresses that receive new-booking notifications get a reminder — "In 15 Minuten trifft {Kunde} für {Raum} ein" — with the customer's phone number, the booking reference and the payment status, so whoever is on the door knows who is about to walk in. Runs on a five-minute cron against a ten-minute window, so a missed tick cannot let a booking slip through unannounced, and each booking is only ever announced once. Confirmed bookings only; anonymous blocks are skipped. A branded `arrival_reminder_admin` email template is bundled and editable on the Email Templates screen.
- **On-site and PayPal figures in the daily summary.** The summary now splits the day's bookings and revenue by how they are paid: the headline table shows how many of the day's bookings and how much of its value came in on site and how much through PayPal, and a new "Nach Zahlungsart" table breaks every method down into bookings, value and money actually received that day. Cash counts as on-site money, which is how the plugin treats it everywhere else; anything else (a bank transfer, say) gets its own row. New template placeholders: `{onsite_bookings}`, `{onsite_revenue}`, `{onsite_received}`, the same three for `paypal` and `other`, and `{payment_method_rows}`.

### Changed
- **A room that is not available can be selected again.** Since 1.8.0 the booking form disabled rooms that were booked or closed for the chosen slot. They are selectable once more — marked red, with the reason still spelled out ("already booked", "closed at this time") and a warning under the field — because an admin may have a reason to move a booking into a taken room and sort the clash out afterwards. The choice is now deliberate rather than blocked.
- **Updating one bundled email template no longer discards edits to the others.** Shipping a change to a single template used to require bumping the design version, which re-synced every template and overwrote anything the team had edited in the admin editor. A template can now be re-synced by name on its own.

### Fixed
- **The Day tab on a phone showed the list, not the day.** On a narrow screen the Day button quietly switched to the day *list* view, so the Day and List tabs rendered the same screen. Every tab now opens the view it names at every width, and the day grid keeps the detailed booking cards it was given in 1.7.2.
- **A one-hour booking's card was cut off in the day view.** The time slots were sized for a two-hour booking on the assumption that two hours was the minimum, but the minimum is one hour: a one-hour card was given 72px and needed 112px. The slots are now tall enough for the shortest booking, and card rows wrap instead of running off when two bookings sit side by side. Affected desktop as well as phones.
- **The room availability warning did not follow the room you picked.** The note under the room dropdown was only set when fresh availability arrived, so choosing an unavailable room by hand left no warning on screen. It now updates with the selection.
- **Customer booking reminders could go out at the wrong time.** The one-hour reminder built its window from the database server's clock while booking times are stored in the site's timezone; where the two differed, reminders fired hours early or late — or never. Both reminder windows are now built from the WordPress clock.
## [1.8.0] - 2026-09-04

### Added
- **Room availability in the booking form's room dropdown.** Moving a booking to another room now respects that room's own diary: every room is marked free or not for the date and time selected, and the ones that cannot take the booking are disabled with the reason — already booked, closed at this time, or under a maintenance lock (which an admin may still override). The booking being edited is excluded from the conflict check, so its own room never looks taken. The checks mirror what the save enforces, including the cooldown between bookings, so the form cannot offer a room the save would then reject.
- **Bank details in the settings** (account holder, IBAN, BIC) under Company Information, used for the cancellation fee.
- **A PDF invoice for the cancellation fee**, attached to the cancellation email. It carries the company header, the customer, the booking reference, the €15 charge and the bank account the fee is to be transferred to, and states that PayPal is not accepted for it. The document is not written to the invoices table — that holds one row per booking — so its number is derived from the booking reference and regenerating it is idempotent.

### Changed
- **The cancellation fee now applies to every cancellation** of an unpaid cash/on-site booking, however far ahead it is cancelled. It was previously charged only inside the cancellation window.
- **The fee is settled by bank transfer, not on-site.** The cancellation email now carries the account holder, IBAN, BIC and the booking reference to use as the payment reference, and says plainly that PayPal is not accepted for the fee. Customers who booked and paid online are unaffected: they are never charged the fee, so their cancellation email carries no fee section and no invoice.

### Fixed
- The cancellation-fee invoice went out unaddressed: the customer name and address live on the customer record for a normal booking, and the invoice was built without it.

## [1.7.2] - 2026-09-04

### Fixed
- **Booking cards were cut off in the calendar's Day view.** A time grid sizes an event to its duration, so a short booking was given less height than its card needs — measured in a browser: the card needs 102px, a two-hour booking was given 74. The day view's slots are now tall enough that even the plugin's shortest allowed booking (two hours) fits a full card, with room for a wrapped line. The week grid keeps its own slot height, where seven columns at this height would be unusable.
- **Overlapping bookings were drawn on top of each other** in Day and Week view, so the card underneath was half covered — the reported case was two bookings both starting at 16:30. They now sit strictly side by side in equal columns.
- **Day view on phones no longer clips the card at all.** The Day button opens the day list rather than the time grid: a list row grows with its content the way a month cell does, so a card with several extras and long names is never cut. Month and week keep their compact pill, where a grid cell has no room for more.

## [1.7.1] - 2026-09-04

### Fixed
- **The plugin's own admin pages were padded away from the admin menu.** `HRB_Admin::add_admin_body_class()` puts `hrb-admin-page` on `<body>`, and every view also styles `.hrb-admin-page` as its page wrapper — so each view's `padding: 24px` landed on `<body>` as well and pushed the whole admin, menu included, in from the viewport. Measured in a browser: it moved the admin menu's right edge from 160px to 184px. The box is now reset on the body; the wrapper keeps its own padding, so the page background runs flush to the menu while the content inside stays inset.
- **Edited stylesheets and scripts were served from cache.** `admin.css` is enqueued from two places — the bootstrap and `HRB_Admin` — and the bootstrap claims the handle first, so its `HRB_VERSION` was the version the browser saw. Any CSS fix shipped without a version bump kept serving the cached file. Both enqueues now version assets by file modification time.
- **A published release could take hours to appear**, even after deliberately pressing "Check again" on the Updates screen: the six-hour release cache outlived it. The cache is now dropped whenever WordPress drops its own plugin-update data, and a force-check goes straight to GitHub instead of answering from cache.
- Removed the negative wrapper margins the views applied at phone widths; they only worked while `<body>` carried the 24px padding and would otherwise pull content off the left edge.

### Changed
- **Day view on phones now shows the full booking card** — customer, room, booking reference, time and price, extras and status — the same detail the list view gives, instead of a compact pill. Month and week views keep the pill, where a grid cell has no room for more. Short bookings are given enough height for the card via `eventMinHeight`, so a one-hour booking is not clipped.

## [1.7.0] - 2026-09-04

### Added
- **Bulk delete on the Old Bookings list**, matching the All Bookings screen: a checkbox column with select-all, a confirmation naming the bookings involved, and a notice reporting exactly which ones could not be removed.

### Fixed
- **PayPal bookings produced a stray "cancelled" payment entry.** Every PayPal booking was opening two pending payment rows — one from `create_booking()`, a second from `create_paypal_order()` — of which the capture only ever completed one. The 1.6.0 fix retired the leftover so it could no longer double the booking total, but a retired row still showed on the payments screen as a payment that never existed. `create_paypal_order()` now attaches the PayPal order to the row that already exists, so only one row is ever created; the post-capture cleanup removes stray rows instead of cancelling them, and is now only a safety net for older data.
- **A failed delete on the Old Bookings list reported success.** `delete_booking()` returns a `WP_Error` on failure, which is truthy, so the notice always said the booking was deleted. (The same bug was fixed on All Bookings in 1.6.0.)

### Changed
- **The daily summary now counts the bookings created that day**, whatever date they are for: a booking taken on the 4th for the 12th belongs to the 4th's summary, together with its value. It previously reported the bookings taking place that day. Money received still counts on the day it actually came in.
- **The daily summary reports booking status and payment status**: confirmed / pending / cancelled counts, plus a payment-status breakdown of those same bookings.
- **The daily summary is rendered from a branded email template** (`daily_summary_admin`), so its wording and layout are editable on the Email Templates screen like every other mail the plugin sends. It follows the same layout as the existing templates — company logo header, details table, highlighted total, company footer.
- **The bulk-delete button now sits in the filters bar** next to Filter and Clear on both booking lists, rather than in a bar of its own. It is outlined rather than solid so a destructive action is not mistaken for Filter, and is visibly disabled until something is selected.

### Database
- New templates added to the bundle are now inserted without re-syncing the existing ones, so the daily-summary template lands without overwriting templates edited in the admin editor (tracked by `hrb_email_bundle_version`).

## [1.6.0] - 2026-09-03

### Added
- **Multiple team notification addresses:** the single "Staff Email" field is replaced by a repeatable list under Company Information. Every address on it receives a notification for each new booking, and addresses can be added or removed at any time. Accepts comma-, semicolon- and newline-separated input, rejects invalid addresses by name instead of dropping them silently, and de-duplicates case-insensitively.
- **Admin booking of past and current time slots:** a walk-in arriving at 11:05 can now be given the 11:00 slot, and bookings that already happened can be entered afterwards. Past slots appear only for admins, clearly marked "Vergangen — buchbar"; the public booking form is unchanged.
- **Bulk delete on the bookings list:** a checkbox column with select-all and a "Delete selected" button. The confirmation names the bookings involved; each delete runs in its own transaction, so one failure does not stop the rest and the notice says exactly which bookings were left behind.
- **Bulk delete on the payments list**, for correcting the books at month-end. The confirmation shows how much money is being struck off. Deleting a payment adjusts the revenue figures only — the booking it belonged to is not changed.
- **Automatic daily summary email:** a scheduled summary of one day's bookings, per-room usage and revenue, sent to its own list of addresses (falling back to the team addresses). Send time is configurable, default midnight. The summary always covers the whole calendar day that ended at the send time, anchored to the scheduled time rather than the moment the job ran, so a WP-Cron job firing minutes late still reports the day that closed. A "Send summary now" button makes the setup verifiable without waiting.

### Fixed
- **PayPal booking totals were doubled** on cancellation and on every subsequent edit (€87.55 shown as €175.10). The total was rebuilt as `SUM(completed) + SUM(pending)` over the payment records, so a stale pending row for an already-captured charge was counted twice. A capture whose lookup missed its own pending row inserted a fresh completed row and left the pending one behind, producing exactly that pair. Totals are now computed by a single shared rule — the original charge counts once, additional charges add up, cancellation fees are excluded — the capture matches its row by gateway transaction id first, and any leftover pending rows are retired once the charge is captured.
- **Cancellation fees were added into the booking total** in the admin edit path (€87.55 + €15 shown as €102.55). They are a separate charge and no longer count towards the booking.
- **Team notifications were lost when the customer address was missing or invalid.** The team mail was dispatched from inside the customer-mail routine, which returns early in that case. It is now sent independently, and one bad recipient no longer suppresses the mail for the rest of the team.
- **Internal room moves no longer email the customer.** Moving a booking from Room 2 to Room 3 is invisible to them; any other edit — on its own or alongside the room change — still notifies as before. Price changes caused by the move do not count as a separate edit.
- **A failed booking delete reported success.** `delete_booking()` returns a `WP_Error` on failure, which is truthy, so the admin notice always said the booking was deleted.
- **Past-time slots were only detected for today,** so on an earlier date the public search offered every slot as available.
- The dashboard revenue chart ran 60 queries per load; it now runs two.

### Changed
- **Dashboard revenue now reflects money actually taken.** "This Month Revenue" and the revenue chart read the payment records instead of `bookings.total_amount`, so a month-end correction on the payments screen shows up on the dashboard. The card also shows how many payments make up the figure. Note this changes the meaning of the number from booked value to collected value.
- The plugin registers `HRB_Daily_Summary`; its cron event is cleared on deactivation.

### Database
- `hrb_staff_email` is migrated once into the new `hrb_staff_emails` list. The migration is option-gated, so an address removed from the list is never resurrected.

## [1.5.0] - 2026-09-03

### Added
- **Automatic updates from GitHub:** the plugin now registers itself with the native WordPress update system and offers new versions published as GitHub releases — the normal "Update now" button on the Plugins screen, no manual zip upload. Release lookups are cached for 6 hours (15 minutes after a failure, so a GitHub outage never stalls admin page loads), the extracted folder is renamed to the installed directory so an update can no longer deactivate the plugin, and a "Check for updates" link on the plugin row forces a fresh check. Private repositories are supported by defining `HRB_GITHUB_TOKEN` in `wp-config.php`.
- **Release pipeline:** pushing a `v*` tag builds the distributable zip, takes the release notes from this changelog, and publishes the GitHub release. The build fails if the tag, the plugin header and `HRB_VERSION` disagree.
- **`CLAUDE.md`** documenting the release workflow and the plugin's bootstrap conventions.

## [1.4.0] - 2026-06-18

### Added
- **Cancellation fee (€15):** A flat €15 fee is charged when a cash/on-site booking is cancelled within the cancellation window (default 24h before start). PayPal/online bookings are excluded, and the fee is **not** charged if the booking was already fully paid. The fee is shown on the booking detail view, as a badge in the All Bookings list, in the cancellation email, and as a labelled, pending row in the Payments screen (payable on-site).
- **Payments "Pending" widget:** Replaces the always-empty "Pending Refunds" stat with the total amount still awaiting collection (respects the active filters).
- **"Cancelled" payment-status filter** on the Payments screen.
- **Branded email templates bundled with the plugin:** all 13 German email templates are now shipped in code (`includes/email-templates-data.php`) and synced into the database automatically on update via a one-time, version-gated migration — no reactivation required. Later manual edits in the admin editor are preserved.
- **German (de_DE) translations** for all newly added strings.

### Fixed
- **Room availability search now honors locks for a selected time + duration.** Previously, when a specific time was selected, the search only checked existing bookings and ignored both master locks and room locks — so a master lock affecting all rooms left most rooms bookable. All availability decisions now run through the single lock-aware engine (master locks, room locks, bookings, cooldown, booking window).
- **No-refund policy on cancellation:** cancelling a booking no longer cancels or refunds an already-**completed** payment — the money is kept and the payment stays `completed`, keeping Total Revenue accurate. Only **pending** (uncollected) payments are cancelled. Removed the automatic refund call on cancellation (the manual per-payment refund button remains).
- **Cancellation fee is no longer counted as part of a booking's payment.** A cancelled + unpaid booking with a collected fee no longer shows its payment as "Pending"; the booking's own payment status/total ignore the fee (it still appears as income in the Payments screen).
- **Marking a cancellation fee as paid** no longer re-confirms the cancelled booking, regenerates an invoice, or sends a payment-confirmation email — it marks only that fee row as collected.
- **Search loading overlay** is now scoped to the results section instead of covering the entire screen.
- **Email logo:** all templates now use the production logo URL (previously several pointed at a `localhost` URL that rendered as a broken image in real emails).

### Changed
- Removed the unused **Refunded / Partially Refunded** options from the Payments status filter (no refunds offered).
- Restyled the booking-confirmation, payment-confirmation and booking-modified emails to match the unified branded German design.

### Database
- Added a `cancellation_fee` column to the bookings table (auto-migrated on update).
