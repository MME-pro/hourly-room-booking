# Changelog

All notable changes to the Hourly Room Booking System plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
