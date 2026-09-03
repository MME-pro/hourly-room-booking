# CLAUDE.md

WordPress plugin: **Hourly Room Booking System** (`hourly-room-booking`).
Repository: <https://github.com/MME-pro/hourly-room-booking> (branch `main`).
Installed locally at `wp-content/plugins/hourly-room-booking-main/` in a Local by Flywheel site.

## Command: `COMMIT RELEASE DEPLOY`

When the user types **COMMIT RELEASE DEPLOY**, run the whole chain without asking for
confirmation at each step:

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
   pull the update themselves through `HRB_Updater`. So finish by confirming the
   workflow run succeeded and the release has the zip asset attached, e.g.
   `curl -s https://api.github.com/repos/MME-pro/hourly-room-booking/releases/latest`.
   Report the release URL back to the user. If the run failed, fix and re-tag.

## Update mechanism

[includes/class-updater.php](includes/class-updater.php) (`HRB_Updater`) plugs the
plugin into the native WordPress update system and serves updates from GitHub
releases:

- Reads `releases/latest`, cached in the `hrb_github_release` transient for 6 hours
  (15 minutes on failure, so a GitHub outage does not stall admin page loads).
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
