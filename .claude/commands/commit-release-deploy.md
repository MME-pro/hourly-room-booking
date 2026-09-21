---
description: Bump the version, commit, tag, publish the GitHub release, then check whether booking.techyza.com has picked it up
---

# COMMIT RELEASE DEPLOY

Run the whole chain without pausing for confirmation between steps. Report at
the end, once — not after each step.

Live site to check: **https://booking.techyza.com**

## 1. Version bump

Decide the next semver from the actual diff (`git diff`, `git status`): patch
for fixes, minor for features, major for breaking changes. Update all three
places, which the release workflow cross-checks against the tag:

- `Version:` in the header of `hourly-room-booking.php`
- `define('HRB_VERSION', '…')` in the same file
- a new `## [x.y.z] - YYYY-MM-DD` section at the top of `CHANGELOG.md`, written
  from the diff in Keep a Changelog form (Added / Fixed / Changed / Removed /
  Database). Say what changed and why it mattered, not just which files moved.

`CHANGELOG.md` is CRLF — keep it that way, or the diff turns into a whole-file
rewrite.

## 2. Commit

```
git add -A
git commit            # summary line ending "(vx.y.z)"
git push origin main
```

## 3. Release

```
git tag -a vx.y.z -m "vx.y.z"
git push origin vx.y.z
```

The tag push triggers `.github/workflows/release.yml`, which verifies the tag
against both version strings, builds `hourly-room-booking.zip`, lifts the
release notes out of `CHANGELOG.md` and publishes the release. There is no `gh`
CLI on this machine — never try to create the release locally, always go
through the tag. If the run fails, fix the cause and re-tag.

## 4. Confirm the release actually published

Check the workflow run and the release itself. `releases/latest` is served from
a cache for a minute or two after publishing, so cache-bust it and prefer the
by-tag endpoint:

```
curl -s "https://api.github.com/repos/MME-pro/hourly-room-booking/actions/runs?per_page=1" \
  | grep -E '"head_branch"|"conclusion"'

curl -s "https://api.github.com/repos/MME-pro/hourly-room-booking/releases/tags/vx.y.z" \
  | grep -E '"tag_name"|"draft"|"state"|"browser_download_url"'
```

The release is only good if the run concluded `success`, `draft` is `false`,
and `hourly-room-booking.zip` is attached with `"state": "uploaded"`. A release
without the zip asset will not install anywhere.

## 5. Check whether the live site has taken it

Deploy for this project *is* the published release — installed sites pull it
themselves through `HRB_Updater`. So the last step is finding out whether
booking.techyza.com has.

Read the version that site is actually running. Its `CHANGELOG.md` is served
directly and is not behind the page cache, which makes it the reliable probe;
the asset query strings are a second opinion:

```
curl -s "https://booking.techyza.com/wp-content/plugins/hourly-room-booking/CHANGELOG.md" \
  | grep -m1 -oE '^## \[[0-9.]+\]'

curl -s "https://booking.techyza.com/?cb=$(date +%s)" \
  | grep -oE 'hourly-room-booking[^"]*ver=[0-9.]+' | sort -u | head -1
```

**Expect it to lag, and do not read that as a failure.** Two caches sit in the
way, both deliberate:

- `HRB_Updater` caches the GitHub lookup for 30 minutes while the site is up to
  date, and 6 hours once an update is already being offered.
- WordPress refreshes its own `update_plugins` transient on a throttle — about
  an hour while someone is on the Plugins screen, twelve otherwise.

And WordPress does not *install* a plugin update on its own unless auto-updates
are switched on for it. Being offered the update and having taken it are two
different things.

So: check once, right after the release. If the site still reports the old
version, say so plainly and tell the user what to do — open **Plugins**, use
the plugin row's **Check for updates** link (that link clears both caches and
forces a fresh lookup), then **Update now**. Offer to re-run the probe
afterwards rather than sitting in a polling loop; a quiet wait of an hour helps
nobody. Never report the site as updated without having read the new version
back from it.

## 6. Report

Give the user, in a few lines:

- the release URL and whether the zip asset is attached
- what booking.techyza.com is running right now, and whether that is the new
  version or the old one
- if it is still the old one: the exact click-path above, and an offer to
  re-check on request

Do not claim a step succeeded that you did not verify.
