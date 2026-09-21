# CLAUDE.md

WordPress plugin: **Hourly Room Booking System** (`hourly-room-booking`).
Repository: <https://github.com/MME-pro/hourly-room-booking> (branch `main`).
Installed locally at `wp-content/plugins/hourly-room-booking/` in a Local by Flywheel
site (`Local Sites/mindmerit`) — the working tree *is* the installed plugin, so the
local copy always reports whatever version was last committed here.
Live site: <https://booking.techyza.com>.

## Command: `COMMIT RELEASE DEPLOY`

The full chain lives in
[.claude/commands/commit-release-deploy.md](.claude/commands/commit-release-deploy.md)
and runs as `/commit-release-deploy`. Typing **COMMIT RELEASE DEPLOY** means the
same thing: follow that file. Keep the two in step — edit the command file, not
a second copy of the steps here.

In outline:

1. **Version bump** — decide the next semver from the nature of the changes
   (patch = fixes, minor = features, major = breaking). Update all three places:
   - `Version:` header in `hourly-room-booking.php`
   - `define('HRB_VERSION', '…')` in `hourly-room-booking.php`
   - a new `## [x.y.z] - YYYY-MM-DD` section at the top of `CHANGELOG.md`,
     written from the actual diff (Keep a Changelog format: Added / Fixed /
     Changed / Removed / Database).

   The release workflow **fails** if the tag, the header and `HRB_VERSION` disagree.

2. **COMMIT** — `git add -A` and commit with a message summarising the change,
   then `git push origin main`.

3. **RELEASE** — tag and push:
   ```
   git tag -a vx.y.z -m "vx.y.z"
   git push origin vx.y.z
   ```
   The tag push triggers [.github/workflows/release.yml](.github/workflows/release.yml),
   which builds `hourly-room-booking.zip`, extracts the release notes from
   `CHANGELOG.md`, and publishes the GitHub release. There is no `gh` CLI on this
   machine — never try to create the release locally, always go through the tag.

4. **DEPLOY** — for this project deploy *is* the published release: installed sites
   pull the update themselves through `HRB_Updater`. Confirm the workflow run
   succeeded and the release has the zip asset attached, then check what the
   live site **https://booking.techyza.com** is actually running:
   `curl -s https://booking.techyza.com/wp-content/plugins/hourly-room-booking/CHANGELOG.md | grep -m1 -oE '^## \[[0-9.]+\]'`
   (that file is served directly, so it is not behind the page cache). Expect a
   lag of up to an hour or more — see the cache TTLs below — and expect the site
   not to install the update by itself unless auto-updates are on for it. Report
   the release URL *and* what the live site is on. If the run failed, fix and re-tag.

## Update mechanism

[includes/class-updater.php](includes/class-updater.php) (`HRB_Updater`) plugs the
plugin into the native WordPress update system and serves updates from GitHub
releases:

- **Two filters, and this is the point.** `pre_set_site_transient_update_plugins`
  is WordPress *building* its update list, which it only does on a throttle —
  about hourly on the plugins screen, twelve-hourly otherwise. That throttle,
  not the plugin, was why a release could sit unmentioned for hours.
  `site_transient_update_plugins` is every *read* of that list, which is what
  actually draws the screen, so a release we already know about appears on the
  next page load. The read filter never touches the network — it uses whatever
  is cached and nothing else, because it would otherwise run on front-end
  requests too.
- **A cron event (`hrb_check_for_updates`, every 5 minutes)** keeps that cache
  warm, so an idle site nobody is browsing still notices a release. It clears
  the cache, re-asks GitHub and calls `wp_update_plugins()`. Cleared on
  deactivation. Worst case from release to visible: five minutes, no clicking.
- Reads `releases/latest`, cached in the `hrb_github_release` transient. How long
  depends on the answer (`HRB_Updater::cache_ttl_for()`): 6 hours once an update
  is pending — it is already being offered on every page load by the read
  filter, so re-asking buys nothing — and **60 seconds** while the site is up to
  date, because that is the state a new release has to be noticed in. A
  six-hour cache in *both* states was the bug behind three "the update never
  arrived" reports. 15 minutes on failure, so a GitHub outage does not stall
  admin page loads.
- The `hrb_release_cache_ttl` filter overrides that, 0 included. Think first:
  unauthenticated GitHub allows **60 calls an hour per IP address**, and when
  that is exceeded GitHub answers 403, which this class caches as "no release".
  Polling harder past the limit produces *fewer* update notices, not more.
  `HRB_GITHUB_TOKEN` raises the ceiling to 5000 an hour and makes an aggressive
  setting safe.
- Compares the tag (leading `v` stripped) against `HRB_VERSION`.
- Prefers the release's `.zip` asset over the source zipball.
- `upgrader_source_selection` renames the extracted folder to the installed
  directory name, so the update does not deactivate the plugin.
- Private repo support: define `HRB_GITHUB_TOKEN` in `wp-config.php`, or filter
  `hrb_github_token`. The token is also used for the download via
  `upgrader_pre_download`.
- "Check for updates" link on the plugins row forces a fresh lookup.

## Architecture notes

- `hourly-room-booking.php` is the bootstrap: a `final` singleton that declares every
  class in `$required_classes`, `require_once`s them from `includes/`, and instantiates
  them in dependency order in `init_components()`. **A new class must be added to both
  lists.**
- All classes are `HRB_`-prefixed singletons with `getInstance()` and a private
  constructor. No autoloader — files are required explicitly.
- `vendor/` (dompdf) is committed so the plugin works when cloned or installed from
  the zip. Do not gitignore it.
- Text domain `hourly-room-booking`; German translations live in `languages/`.
  New user-facing strings need a `de_DE` translation.
- PHP 7.4 minimum, so no PHP 8-only syntax in `includes/` (the bootstrap file uses
  `declare(strict_types=1)` and typed properties; the class files do not).
