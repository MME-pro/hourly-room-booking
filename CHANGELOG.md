# Changelog

All notable changes to the Hourly Room Booking System plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.23.0] - 2026-09-23

### Added
- **A hand-made no-show gets its own mail, not the day's summary.** Both cases used the same template in 1.21.0, which meant marking one booking at the desk produced a summary-shaped mail with a count of one and a total value. They are different pieces of news, so they are now different templates: `no_show_summary_admin` still reports the day's no-shows and what they were worth, and the new `no_show_status_change_admin` reports one booking and one fact — its reference, the status it held, and the status it holds now.

### Changed
- **The customer is no longer told their booking was "modified" when it is marked No Show.** Marking a booking no-show from the edit form counted as a booking change like any other, so the customer who never turned up received an email saying their booking had been updated. They do not need to hear it — the desk does, and the status-change mail above is what says so. Every other status change still notifies the customer exactly as before.

### Database
- The email template bundle version is bumped, so existing sites are seeded with the status-change template on the next admin load. Templates the team has already edited are left untouched.

## [1.22.0] - 2026-09-23

### Changed
- **The length of a booking no longer decides how it is paid for.** A booking of four hours or more could only be paid by PayPal: the booking form hid the on-site option and forced PayPal from four hours up, the server refused any public booking of that length that named another method, and processing an on-site payment for one was refused outright. Both methods are now offered for every booking, whatever it runs to — a customer who wants to pay at the desk for a full day can.
- The rule lived in four places, and all four are gone: the validation in `HRB_Booking_Manager::create_booking()`, the refusal in `HRB_Payment_Handler::process_onsite_payment()`, and the duration check in both copies of the booking form — including the branch that re-applied it after the anonymous-booking option was switched off.
- The payment policy shown on the booking form says so: paying by PayPal or on site is offered whatever the booking's length. The separate note that bookings of four hours or more cannot be refunded stays, as its own line — that rule is unchanged.

## [1.21.0] - 2026-09-23

### Added
- **A no-show now tells someone.** Until now a booking became a no-show in silence: the end-of-day pass turned every past unpaid booking into one, voided its money, and nobody found out unless they went looking at the list. The team that reads the daily summary is exactly the team that wants to know, so that is who is told. One mail per pass, listing every booking it marked — reference, customer, room, date and time, amount — with the count and the total value that will never be collected.
- **Marking a booking No Show by hand sends the same mail**, for that one booking, with the reason line saying it was marked at the desk rather than by the clock. It is wired into both paths a status can change through — the booking edit form and the status action — because either one is someone at the desk reaching the conclusion early, and it fires only on the transition, so re-saving a booking that is already a no-show sends nothing.
- The mail is a branded template, `no_show_summary_admin`, editable on the Email Templates screen like every other mail the plugin sends. Recipients are the daily summary's own list, falling back to the team notification addresses the same way. `hrb_no_show_summary_recipients` filters that list, and returning an empty array switches the notice off.
- Deliberately not tied to the daily summary's on/off switch: that governs a scheduled report of a day's figures, while this is news about bookings that will never be paid.

### Fixed
- A test pinned the email bundle version to one exact literal, so adding any later template to the bundle broke it. It now checks what it was actually trying to prove — that the version is no older than the day the template it cares about joined the bundle.

### Database
- The email template bundle version is bumped, so existing sites are seeded with the new template on the next admin load. Templates the team has already edited are left untouched.

## [1.20.0] - 2026-09-23

### Changed
- **A booking's payments are now found by the booking, not by one transaction id.** The booking details screen used to print the transaction id carried on the booking row and link to a Payments search for that string. A booking can have several payment rows — a deposit, a remainder, a cancellation fee — and only one of them is that id, so the other rows were unreachable from the booking they belong to. The label, the id and the *View Payment* link are replaced by a single **View Transaction** button that opens Payments filtered on `booking_id`, which lists all of them. The filter is kept across the screen's own filter form, so narrowing by status or method stays inside that booking.
- **A cancelled booking now states what is actually owed.** It used to be presented as a bill for the room: Amount `85.00 €`, and a breakdown of base price and extras totalling the same, with the cancellation fee added underneath as an afterthought. Nobody owes that — the room is not being provided. The breakdown now reads Booked Service, Cancellation Deduction (the same figure taken straight back off), Cancellation Fee, and **Total Amount Due**, and the Amount in Payment Information is the fee alone. The payment method on a cancelled booking reads Bank Transfer, which is how the fee is settled.
- Both changes are display only. No booking, payment or amount is written differently, and a booking that is not cancelled shows exactly what it showed before.

## [1.19.1] - 2026-09-23

### Changed
- **Two revenue headers that 1.18.0 missed are now Super Admin only.** The Dashboard's *This Month Revenue* card and the *Total Revenue* card on Reports & Analytics were still gated on `hrb_view_financials`, which a Room Booking Admin holds. So on a client site the client went on seeing the one figure the stats capability was introduced to keep back, while the Payments screen already withheld it — the rule existed, two screens simply had not been moved onto it. Both cards now ask `hrb_can_view_stats()`, the same question Payments asks.
- Nothing else changes hands. An Admin keeps every figure they actually work with: a booking's price, the payment records, the Revenue Analysis chart and the room-by-room revenue table further down the Reports screen. What is withheld is only the headline summary sitting above the screen — our read on how the installation is doing, not the client's books.

## [1.19.0] - 2026-09-22

### Changed
- **A booking now belongs to its day, not to its end time.** A booking from 06:00 to 07:00 used to become a *past* booking at 07:01 — it dropped off the desk's screens while the day it belonged to was still being worked. It now stays until 23:59 that night and turns over at midnight. Everything keyed to "past" moves with it, so the three can never disagree: which bookings an Employee is shown, when a booking becomes **completed**, and when an unpaid one becomes a **no-show**.
- The practical effect on no-shows is the point of the change: a customer who has not arrived by 10:00 now has the rest of the day to walk in and pay, instead of being written off as a no-show at 10:01.
- A booking that runs past midnight is measured from the day it *finishes* on, not the day it started. 23:30–02:30 started on the 24th finishes on the 25th and turns over at midnight on the 26th. Keying it to the start date would have made it past at midnight on the 25th, while it was still running. The new boundary is `HRB_Capabilities::becomes_past_at()` with a SQL twin, `becomes_past_at_sql()`, verified to return the same two answers against MySQL 8.4.

### Added
- **Bank Transfer is a payment method again, for the admin only.** It is offered when creating or editing a booking in the admin and nowhere else — the public booking form does not render it, and the AJAX endpoint that backs that form refuses it. The refusal is what matters: that endpoint is handed raw `$_POST`, so the method is unlocked by an explicit argument in the validator rather than by any flag carried in the submitted data, which a customer could otherwise set themselves.
- A booking taken by bank transfer is confirmed straight away with its payment left pending, and its invoice is raised immediately — that invoice is what the customer pays against. When the money lands, **Mark Payment as Complete** settles it, the same button cash bookings use. The Payments screen has a Bank Transfer filter to find them.
- The account from **Settings → Bank Transfer** — bank name, account holder, IBAN, BIC and the reference to look for on the statement — appears on the booking form as soon as Bank Transfer is selected, so whoever reconciles the booking can see which account the money should be on without leaving the page. It also appears for the "Paid by bank transfer" note added in 1.18.0, which remains: the method says how a booking is *to be* settled, the note records that it *was*.
- A booking whose method is bank transfer keeps it even if the setting is later switched off, so turning the option off can never silently rewrite an existing booking's payment method.

## [1.18.1] - 2026-09-22

### Fixed
- **A room lock now blocks the slots that run past midnight.** Reported from the live site: a room locked from 17:13 on one day to 18:13 the next still offered 22:00–00:00, 22:30–00:30, 23:00–01:00 and 23:30–01:30 on the first day. The time-slot picker pinned both ends of a slot to the booking date, so a slot finishing after midnight came out as "22:00 to 00:00 **on the same day**" — a window running backwards, which fails every overlap test it is put through. The afternoon slots were blocked correctly, which is what made the lock look like it was half-working rather than broken.
- **A lock sitting entirely on the following day is now seen at all.** The same code only ever fetched the booking date's own locks, so a slot starting at 23:30 and running into the next morning was never compared against a lock waiting for it there. The lock lookup now covers the whole span a slot starting on that date can reach.
- Bookings themselves were never at risk: `HRB_Database::is_slot_locked()`, which guards the save, has always rolled a slot's end onto the next day correctly — so a locked slot could not actually be booked through it. What was wrong was the second, hand-written copy of that comparison used to *draw* the picker. Both lock checks now go through one `slot_overlaps_lock()` helper, so the two can no longer disagree. The admin booking form reads the same endpoint and is fixed with it.

## [1.18.0] - 2026-09-22

### Added
- **An internal note for a booking settled by bank transfer.** Creating or editing a booking in the admin now offers a **Paid by bank transfer** checkbox. It is a note for your own records and nothing more: it is never shown to the customer, it is not offered anywhere in the public booking flow, and it leaves the booking status, the payment status and the payment method exactly as they were. Bank transfer is deliberately *not* a payment method here — the desk settles the money by hand and records that it did.
- The account from the new **Settings → Bank Transfer** tab — bank name, account holder, IBAN, BIC and the reference to look for on the statement — is shown beside the checkbox once it is ticked, so whoever reconciles the booking can see which account the money should be on without leaving the form. The reference accepts `{booking_reference}`, which is filled in per booking. Account holder, IBAN and BIC fall back to the company bank details the cancellation-fee invoice already uses, so the IBAN does not have to be typed twice. A ticked booking carries the note on its detail view.
- **A Super Admin role, and the stats headers now belong to it.** Three roles run the plugin instead of two. The row of summary cards above a screen — "Total Revenue", "This Month" — is our read on how an installation is doing rather than the client's, so it moves behind the new `hrb_view_stats` capability and the `hrb_super_admin` role. An Admin still sees and works every figure they run the business on: a booking's price, every payment record, the reports screen. They are simply not given the headline across the top. A WordPress administrator does not inherit this, because on a client site the client usually is one.
- **Bookings nobody turned up for settle themselves.** An hourly job marks a past, unpaid, uncancelled booking as **no-show** and sets its payment status to the new **nil** — the void. A no-show owes nothing and paid nothing, so the amount drops out of every pending figure without ever being counted as taken. "Cancelled" would have claimed the booking was called off, which is the opposite of what happened: the slot was held, the room stood empty and nobody came.

### Fixed
- **A failed update check no longer hides a release the site already knew about.** Found in the wild: on some hosts the GitHub lookup succeeds from wp-admin and fails from WP-Cron. The cron runs every few minutes, and each failure used to overwrite a perfectly good release with an empty answer for fifteen minutes — open the Plugins screen inside that window and the update was invisible, on a site that could have fetched it fine from where you were standing. A failed lookup now keeps the last answer that had a version in it and only records the error alongside, so the failing path can no longer hide what the working path found.
- **Ticking an internal note no longer emails the customer.** An admin edit decides whether to send a "your booking was modified" notice by diffing the customer-facing fields, and the old check matched a room move *exactly* — so an edit with no customer-facing change at all fell through to "notify". Saving the edit form untouched, or ticking only the new bank transfer note, mailed the customer about something they cannot see. An edit that changes nothing they would notice now sends nothing.

### Database
- `hrb_bookings` gains `paid_by_bank_transfer` (`tinyint(1)`, default `0`). Added on activation and by a one-time, option-gated migration on the next admin page load, so existing installs pick it up without reactivating.

## [1.17.0] - 2026-09-21

### Added
- **A booking that is over drops off an Employee's screens.** A booking from 06:00 to 07:00 is the desk's business until 07:00; at 07:01 it is done with, and it leaves their Bookings list, their Payments list and the recent-bookings list on the dashboard. An Admin keeps the whole history. The new `hrb_view_past_bookings` capability draws the line, and the **Old Bookings** screen — which is nothing but finished bookings — now belongs to it as well.
- The cut is measured from the booking's **end**, not its date, so a booking running until 02:30 is still live at 01:00 the next morning rather than having vanished at midnight. `HRB_Capabilities::booking_ends_at()` rolls the end onto the next day when it does not follow the start, the same way the calendar feed does.
- The filtering happens in SQL, so a finished booking is not fetched and paged around before being hidden: the list count an Employee sees matches the rows they get. "Now" is handed to the query from PHP rather than taken from `NOW()`, because the database server's clock and the plugin's timezone are not the same thing.

### Note
- The boundary is inclusive at the end: a booking ending at 07:00 is still shown at 07:00 and gone at 07:00:01. The desk keeps a booking for exactly as long as it is running.
- This hides finished bookings from the *lists*. The calendar still shows them — an Employee sees the booking, and since 1.15.0 not its price once the day has passed.

## [1.16.1] - 2026-09-21

### Fixed
- **The View button on the Payments screen did nothing for an Employee.** The request came back refused — `hrb_get_payment_details` wanted `hrb_manage_payments`, which 1.15.0 had not given the role — and the handler had no `else` branch, so the refusal arrived and nothing on screen changed. That is indistinguishable from a dead button. An Employee now holds `hrb_manage_payments`, so the modal opens; and a refusal or a failed request says so instead of passing in silence.
- **The Refund, Mark as Completed and Cancel buttons are back on the Payments screen.** 1.15.0 hid them from an Employee on the grounds that the server would refuse them anyway. Giving the role the capability was the right end to fix: the desk works the payment list, so the buttons belong there and now do what they say.

### Note
- An Employee can now also bulk-delete payment records, which the same capability governs. Say so if that should be an Admin's alone and it can be split out.
- The books are unchanged: revenue cards, the four figures above the payment list, reports, price configuration and exports remain with an Admin.

## [1.16.0] - 2026-09-21

### Changed
- **The booking window bounds the slot's start again, as it did in 1.12.0.** 1.14.0 read these two settings as an office's opening hours — the clock on the wall when a booking is placed — and that was the wrong reading. They bound when a **booking may start**, and the end is the latest time a slot may *open* at, not the time everything must be finished by. With `Booking End Time` at 23:30 and a three-hour booking, the last slot offered is **23:30–02:30**: it runs past the window and past midnight, which is the whole point. The wall-clock check on submission is gone, along with the timezone helper it needed.
- **The time-slot picker, the calendar's slot list and both search filters filter by the window again**, so the last slot offered is the last one that can actually be saved.
- **The settings are called "Booking Start Time" and "Booking End Time" again.** 1.13.1 renamed them to Opening/Closing on the 1.14.0 reading; with the slot meaning restored the old names fit, and the description now spells out the case that caused the confusion: the end is the latest time a booking may START, not the time it has to be over by, with the 23:30 → 23:30–02:30 example written out.

### Note
- **Check the two settings after upgrading.** A site that was set up under 1.14.0's reading has its real opening hours in these fields; under this release the end is the last *startable* time. For "bookings can start any time from 08:00 until 23:30, whatever their length", set 08:00 and 23:30.
- Nothing else from 1.15.0 moves: the Admin/Employee split, the Amount column for the desk, the Payments screen, the calendar's past/future rule and the update fixes are all unchanged.

## [1.15.0] - 2026-09-21

### Added
- **An Employee sees what a booking costs, without seeing the books.** 1.13.0 drew one line through all money, which took the Amount column away from the desk along with the revenue cards. There are two lines now. `hrb_view_booking_amounts` is the desk's question — what does this customer owe — and an Employee has it: the Amount column in the booking list and on the dashboard, a booking's own total and pricing breakdown, its payment records, the running total while taking a booking, the Amount column in a customer's history. `hrb_view_financials` stays the books — revenue cards, totals across bookings, the reports screen, price configuration, exports — and stays with an Admin.
- **The Payments screen is open to an Employee.** They work the payment list; the four figures above it (Total Revenue, This Month, Total Transactions, Pending) are the books and are not drawn for them. Refunding, cancelling and deleting payment records remain an Admin's, and those buttons are no longer rendered for someone who cannot use them.
- **On the calendar, an Employee sees a booking's price while it is still ahead of them, and not once the day has gone by** — money still to be taken is desk work, a past day's takings are the books. An Admin sees both. The figure is left out of the calendar feed rather than hidden in the markup, so it is not readable off the network response either.

### Fixed
- **A site could never be told about an update, and never told why.** The release cache is filled by the five-minute cron event and by WordPress rebuilding its update list — and on a site whose WP-Cron does not fire, and whose core update check cannot reach api.wordpress.org, neither happens, so the cache stays empty and the read filter added in 1.13.0 had nothing to serve. It now fills the cache itself on an admin screen when it finds it empty. The front end never reaches this code, and the 60-second cache holds it to one lookup a minute.
- **The plugins row says what the last update check actually found** — "Latest release: 1.15.0", or "Update check failed: …" with the reason GitHub gave, next to the "Check for updates" link. Silence was the hardest version of this bug to diagnose: clicking the link and seeing nothing change could mean anything from a blocked host to an exhausted API allowance, and told nobody which.

### Note
- Existing Employees gain `hrb_view_booking_amounts` and `hrb_view_payments` on upgrade; the role is rebuilt on every admin load, so nothing needs reinstalling.
- The Old Bookings screen stays Admin-only for amounts, on the same reasoning as the calendar: a booking whose day has passed is a figure in the books rather than something the desk still has to collect.

## [1.14.0] - 2026-09-21

### Changed
- **The booking hours are opening hours, not a limit on the slot.** "Booking Opening Time" and "Booking Closing Time" now do what their names say: they are the hours in which a booking may be *placed on the site*, like an office's opening hours, and they have nothing to do with which slot is being booked. A customer at the desk at 23:00, while it is still open, can book a room for 05:00 the next morning — that is the case these settings exist for. What is refused is taking a booking at 23:45, when the place is shut. Since 1.12.0 they had instead bounded the *start time of the slot*, which is not what they were ever for.
- **Every hour of the day is bookable again.** The time-slot picker, the calendar's slot list and both search filters offer all 24 hours. A room's own "Bookable hours" still bound the slot where an admin has set them, because a room that is shut at 03:00 genuinely cannot host a booking starting then — that is a different question from whether the desk is open.
- **Admins are exempt**, as they already are from the past-date, advance-window and duration rules. They are the desk, and they occasionally have to put a booking right after hours.
- The check reads the clock in the plugin's own timezone (`hrb_timezone`) rather than the server's, which on shared hosting is usually UTC and would close the desk an hour or two early. An unusable timezone setting falls back to WordPress's local time rather than refusing bookings.
- `HRB_Booking_Manager::is_start_within_booking_window()` is now `is_time_within_window()`: the arithmetic is the same, but what is handed to it is the clock, not the slot, and the name should say so.

### Note
- **Check your closing time after upgrading.** Under 1.12.0–1.13.1 a site wanting bookings around the clock had to set the closing time to 23:30 or 24:00. That value now means "the desk is open until 23:30", which may be later than you actually take bookings. Set these two to your real opening hours.
- Nothing here changes how long a booking may be. The duration rules are unchanged: minimum 2 hours, 12 for a public booking, 24 for one an admin enters.

## [1.13.1] - 2026-09-21

### Changed
- **The two booking-window settings are now called "Booking Opening Time" and "Booking Closing Time".** They were "Booking Start Time" and "Booking End Time", which read as though they bounded the booking itself — the very thing they stopped doing in 1.12.0. They bound the window in which a booking may be *taken*, so they are named after opening and closing hours instead.
- **Their descriptions say what they actually do.** The closing time now reads: these two times are the window a booking may be started in, not how long it may run — a booking can be any length up to 24 hours, and one that starts before closing runs its full length, past closing and past midnight. Translated for `de_DE`.
- The docblocks and comments that name these settings follow the new wording, so the code and the screen agree.

### Note
- The option keys are unchanged (`hrb_booking_start_time`, `hrb_booking_end_time`). Renaming them would have discarded the times every site has already set; only what the screen calls them has moved.
- Nothing about the rules changed in this release. A site still showing a restriction after upgrading is one whose closing time is set earlier than intended — the setting, not the code.

## [1.13.0] - 2026-09-21

### Added
- **Two roles instead of one: Admin and Employee.** The plugin shipped a single "Room Booking Staff" role that carried every capability it defines, money included. It is now **Room Booking Employee** and runs the desk — bookings, the calendar, customers, the room diary, extras stock, and marking a booking paid — while seeing no figures at all. A new **Room Booking Admin** role carries everything. WordPress administrators are unaffected.
- **`hrb_view_financials`**, one capability that draws the money line, defined with the role map in `HRB_Capabilities`. Every figure in the admin screens is behind it, so hiding a new one is a matter of asking the same question rather than inventing another rule. Hidden from an Employee: the revenue cards and the revenue line on the dashboard chart, the Amount column in all four booking lists, Total Spent, a booking's price, pricing breakdown, payment summary and invoice, room and extras prices in both the lists and the forms, the calendar's per-booking price and month revenue, the live price summary while taking a booking, and the costs on the adjust screen. The Payments and Reports screens, settings and exports are gone from their menu entirely.
- **The figures are left out of the AJAX responses too**, not just hidden in the markup: dashboard stats, the chart series, calendar events and stats, the booking and customer detail modals, and the room and extra detail endpoints. A figure an Employee may not see is never one network response away.

### Fixed
- **"Export Payments" downloaded a file containing `0`.** The button asked admin-ajax for `action=export_payments`, and WordPress only dispatches an action it has a `wp_ajax_{action}` hook for. There was no such hook, so what came back was admin-ajax's "nothing matched" body — a single `0` byte, saved as the CSV. The export is now a real handler: it reads the same filters the screen was showing (status, method, date range, search), streams proper CSV, and opens in Excel as UTF-8 rather than mojibake.
- **"Export Report" had the identical bug** and its button had been commented out rather than fixed. Both are working, and the Reports screen and its export now resolve the selected date range through one shared rule, so the file cannot cover a different period than the screen.
- **The booking CSV export had no capability check at all** — only the shared nonce, which every plugin admin page carries. It requires `hrb_export_data` now, like every other export.
- **A released update could go unmentioned for hours, or never arrive at all.** `HRB_Updater` only hooked `pre_set_site_transient_update_plugins` — WordPress *building* its update list, which it does on a throttle, and which core abandons early when it cannot reach api.wordpress.org. On a site with awkward outbound HTTP that filter may never fire, so no amount of clicking "Check for updates" produced anything. The release is now also injected on `site_transient_update_plugins`, every *read* of that list, which is what actually draws the plugins screen. The read filter never touches the network — it serves whatever is cached, because it would otherwise run on front-end requests too.

### Changed
- **A release is noticed within about five minutes now, with nobody clicking anything.** The lookup cache is 60 seconds while the site is up to date (was 30 minutes), and a new `hrb_check_for_updates` cron event re-asks GitHub every 5 minutes so an idle site nobody is browsing still notices. Once an update is pending the answer is held for 6 hours, because the read filter is already putting it on screen on every page load. The event is cleared on deactivation.
- **`hrb_release_cache_ttl`** filters that cache, 0 included — but unauthenticated GitHub allows 60 calls an hour from one address and answers 403 beyond it, which this class caches as "no release". Polling harder past the limit produces *fewer* update notices, not more. Define `HRB_GITHUB_TOKEN` to raise the ceiling to 5000 an hour first.
- Hidden price fields no longer save as zero. A user who may not see money is not shown the rate fields, so their post carries none; the stored rate is kept rather than overwritten with 0.
- `COMMIT RELEASE DEPLOY` is a real command now — `.claude/commands/commit-release-deploy.md`, invokable as `/commit-release-deploy` — and it finishes by reading back what the live site is actually running instead of stopping at the published release.

### Note
- **Existing staff users lose their financial access on upgrade.** The old role wrote every capability onto each user record as well as onto the role, and a user-level capability outranks the role — so the upgrade revokes them from the users, not just from the role. Anyone who needs full access should be moved to **Room Booking Admin**.
- **Extras stock is still not cross-midnight aware.** `HRB_Extra_Stock_Manager` matches overlapping bookings by date plus time-of-day, so the hours an overnight booking runs into the next day are not counted against an extra's stock.

## [1.12.0] - 2026-09-21

### Changed
- **"Booking Start Time" and "Booking End Time" now bound when a booking may *start*, not when it must be over.** The two settings exist because someone has to be there to take the booking; how long the session then runs was never their business. A booking starting at 23:30 in an 08:00–23:30 window runs its full length — five hours, six, past midnight — and is no longer refused for ending "after closing". What the window still refuses is a booking *starting* outside it. Both ends are inclusive: a window ending 23:30 makes 23:30 itself bookable, so an end of 20:00 now offers a 20:00 start where it used to stop at 18:00. **Sites that set the end time to mean "must be finished by" should move it to the last time they want a booking taken.**
- **A room's own "Bookable hours" follow the same rule.** They say when a booking in that room may begin; what happens after midnight is not their concern. Rooms left at the 00:00–24:00 default are unaffected.
- **Both windows have to allow a start, rather than the room's hours replacing the global ones.** The picker used to offer slots inside a room's hours that the save then rejected for being outside the global window.
- The `[room_calendar]` grid covers the whole day instead of stopping at the booking window, so the hours a late booking runs into are visible. The window itself is still marked as business hours.

### Fixed
- **Admins could never book more than 12 hours, despite being allowed 24.** `validate_booking_data()` carried a duplicate duration check, hardcoded to 12, that ran after the admin allowance and overruled it. The duplicate is gone.
- **The time-slot endpoint refused any duration over 12 hours for everyone**, while the admin booking form offers 2–24. It now matches the save: 12 hours for the public, 24 for a capability-checked admin.
- **A booking running past midnight was drawn as an event ending before it began.** The calendar feeds pasted the end time onto the booking's own date, so 23:30–05:00 read as a negative span. New `HRB_Booking_Manager::end_datetime()` rolls the end to the next day.
- The search and filter time dropdowns came out empty when the end time was set to `00:00`, because the hour loop ran from 8 down to 0. They ask the window rule now, like everything else.
- `HRB_Calendar::get_available_time_slots()` could build an end time of `25:00:00` for a slot starting at 23:00.

### Note
- One rule decides all of this — `HRB_Booking_Manager::is_start_within_booking_window()` — and the save path, the slot picker, both calendars and the two search filters all ask it, so they cannot drift apart. It reads an end of `00:00` or `24:00` as midnight at the end of the day, and a window whose end precedes its start (20:00–02:00) as wrapping midnight. Covered by `tests/unit/test-booking-window.php`.
- **Extras stock is not yet cross-midnight aware.** `HRB_Extra_Stock_Manager` matches overlapping bookings by date plus time-of-day, so the hours an overnight booking runs into the next day are not counted against an extra's stock and the same item could be taken twice there. This was already reachable with an end time of `24:00`; it is more reachable now.

## [1.11.5] - 2026-09-10

### Fixed
- **"Edit Booking" in the calendar did not open the booking.** The button sent `booking_id=`, but the bookings screen reads `$_GET['id']` — so it stayed at 0, the edit form never rendered, and the page fell through to the booking list. It now uses the same URL as the Edit link in that list: `admin.php?page=hrb-bookings&action=edit&id=…`.
- **The same broken link in two other places.** The booking link in the Payments list and the "View" link on the new-booking toast both sent `booking_id=` as well, and both landed on the list instead of the booking. Every edit link in the plugin now builds the same URL.
- The Edit button carried a stale inline `onclick="editBooking()"` with no argument alongside the handler that supplies the booking id. It is gone, so there is no path that can navigate to `id=undefined`.
## [1.11.4] - 2026-09-10

### Fixed
- **Overlapping bookings on a phone were unreadable.** Three at once in the day view left each card about 90px wide, and the full card — customer, room, reference, time, price, extras, status — wrapped into roughly one letter per line with the status badge spilling out of the bottom. A card that narrow now shows only what identifies it: customer, time and price down to 170px, customer and time below 120px. Everything else is one tap away in the booking details. A booking that has its column to itself is unchanged.
- **The week view on a phone shredded into 15px slivers.** Seven columns on a phone leave about 48px each, which no amount of trimming rescues, so the stack is capped there and anything past it becomes a "+n more" link.

### Note
- How wide a card ends up depends on how many bookings overlap it, which CSS cannot see, so the width is measured in the browser and the card is tagged from there. The measurement watches for the positions FullCalendar writes rather than waiting a fixed moment after render — waiting was a guess, and on the week view it guessed wrong and tagged nothing.
- Desktop is deliberately untouched. A desktop week column with three overlapping bookings is just as cramped, and can be trimmed the same way if wanted.
## [1.11.3] - 2026-09-10

### Fixed
- **A booking card in the calendar showed only its start time.** It read "14:30" for a booking that runs until 17:30, because FullCalendar's `timeText` is just the start in a month cell. The card builds the range from the event's own start and end now, so every view reads "14:30 – 17:30". An event with no end — an all-day block — falls back to what FullCalendar worked out, so nothing renders a dangling dash.
## [1.11.2] - 2026-09-10

### Removed
- **The "Noch einzuziehen" list is gone from the daily summary.** The mail now ends with the labelled table; the figures it carried are still there as *Barzahler*. `{unpaid_booking_rows}` and its renderer still ship, so a template that was edited to keep the list is unaffected.

### Note
- No customer name appears in the bundled summary any more — it is figures only. The escaping test that used to run through that list now exercises the renderer directly, so the coverage did not go with it.
## [1.11.1] - 2026-09-10

### Changed
- **The daily summary was rebuilt to the supplied design.** A gold title band carrying "Terminübersicht {date}", then five figure cards — appointments (with their total value), money in through PayPal, cancellations, cancellation fees, and the on-site payers with their count and sum — and below them the same figures written out as a labelled table: Terminanzahl, Zahlungen über PayPal, Stornierungen, Stornogebühr, Barzahler. The list of who still has to pay follows underneath.
- **The prose paragraphs were dropped.** The table now carries what they said, and reading the same figures three times over made the mail longer without making it clearer. The list naming who owes money stays — nothing else carries those names.

### Added
- **`HRB_Daily_Summary::day_tally()`** counts the day once and hands the same numbers to the cards, the table and the sentences, so the three cannot drift apart. New placeholders drawn from it: `{appointments_count}`, `{appointments_value}`, `{cancelled_count}`, `{onsite_count}`, `{onsite_sum}`, `{paypal_count}`, `{paypal_sum}` and `{summary_table_rows}`.
## [1.11.0] - 2026-09-10

### Changed
- **The daily summary reports the day's appointments, not the day's intake.** Every figure in it used to count bookings by the day they were *entered* — so a booking taken this morning for three weeks' time was in today's mail, and the appointments actually happening today were not. All twelve queries now key on `booking_date`: how many appointments there are, what they are worth, what has been received for them, what is still owed, the per-room and per-method breakdowns, and the cancellation fees. The mail describes one day's diary and nothing else.
- **The written summary follows the same shape.** "Am 11.09.2026 stehen 10 Termine an, im Wert von insgesamt 1.000,00 €. 3 Termine wurden bereits über PayPal bezahlt: 300,00 €. 7 Termine zahlen vor Ort: 700,00 €." Anything cancelled for that date is reported on its own line with its value, and the list of who still has to pay follows underneath.
- **Wording throughout was corrected to match**: the first card is *Termine* rather than *Buchungen*, the section heading is *Terminübersicht*, the subject reads "Termine am {summary_date}", and the card sub-labels say "für diese Termine" instead of "heute".

### Note
- **The send time now decides which day you get.** The summary covers the day that ended at the configured send time, so at 00:00 it reports yesterday's appointments and at 06:00 it reports today's. If the mail is meant as a morning briefing — who is coming and who still owes money — set the send time to a morning hour rather than midnight.
## [1.10.6] - 2026-09-09

### Fixed
- **"2 vor Ort und die 2 PayPal".** The list of payment methods was joined with a bare `and`, a one-word string the catalogue already translated as "und die" for a different sentence. It has a pattern of its own now; the existing entry was left alone for whatever else uses it.
- **The sentence said "Verbleibend" but counted more than that.** "Still standing" means "not cancelled", which includes bookings nobody has confirmed yet. It now counts and names the confirmed ones — so the figure agrees with the Buchungsstatus panel beneath it — and says "Noch unbestätigt: n" separately when anything is waiting, rather than folding it in silently.

### Changed
- **The booking and payment status panels sit below the written summary**, so the mail reads as prose first and reference figures after.
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
