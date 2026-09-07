<?php
/**
 * Tests for how long the updater holds on to a GitHub release lookup.
 *
 * This is the rule that decides whether a site notices a new release, and it
 * has been wrong twice. The release payload was cached for six hours whatever
 * the state of the site, so after a release went out every install kept
 * answering "up to date" from a cache holding the *previous* release — while
 * GitHub returned the new one correctly the whole time. Only a manual "Check
 * again" (which sets force-check) broke through.
 *
 * The rule now turns on whether an update is already pending: long while one
 * is being offered, short while the site is up to date, because that is
 * exactly the state in which a new release needs to be noticed.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-updater-cache.php
 *
 * @package HourlyRoomBooking
 * @since 1.9.1
 */

define('ABSPATH', __DIR__);
define('HRB_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

$failures = 0;

function check(string $label, $actual, $expected): void {
    global $failures;

    $passed = $actual === $expected;
    if (!$passed) {
        $failures++;
    }

    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);

    if (!$passed) {
        printf(
            "    expected: %s\n    actual:   %s\n",
            var_export($expected, true),
            var_export($actual, true)
        );
    }
}

require_once HRB_PLUGIN_DIR . 'includes/class-updater.php';

$long  = HRB_Updater::CACHE_TTL;
$short = HRB_Updater::IDLE_TTL;

// ---------------------------------------------------------------------------
// The two windows
// ---------------------------------------------------------------------------

echo "\n-- the two windows --\n";

check('the short window is at most half an hour', $short <= 1800, true);
check('the long window is longer than the short one', $long > $short, true);

// WordPress refreshes its own plugin update transient about once an hour when
// an admin is on the plugins screen. A cache longer than that would swallow
// those checks, which is how the bug worked.
check('the short window is shorter than WordPress own check interval', $short < 3600, true);

// ---------------------------------------------------------------------------
// Which window applies
// ---------------------------------------------------------------------------

echo "\n-- while the site is up to date --\n";

// This is the case the bug lived in.
check('same version re-checks soon', HRB_Updater::cache_ttl_for('1.9.0', '1.9.0'), $short);
check('a running site on the release re-checks soon', HRB_Updater::cache_ttl_for('2.0.0', '2.0.0'), $short);

// A site can be ahead of the published release mid-development.
check('a site ahead of the release re-checks soon', HRB_Updater::cache_ttl_for('1.8.0', '1.9.0'), $short);

// A lookup that failed stores an empty payload; holding that for six hours
// would keep a site blind long after GitHub recovered.
check('an empty version re-checks soon', HRB_Updater::cache_ttl_for('', '1.9.0'), $short);

echo "\n-- while an update is pending --\n";

// Nothing is gained by re-asking: the update is already on the plugins screen.
check('a newer patch is held', HRB_Updater::cache_ttl_for('1.9.1', '1.9.0'), $long);
check('a newer minor is held', HRB_Updater::cache_ttl_for('1.10.0', '1.9.0'), $long);
check('a newer major is held', HRB_Updater::cache_ttl_for('2.0.0', '1.9.9'), $long);

// 1.10.0 is newer than 1.9.0 — string comparison would get this backwards, so
// the rule has to be using version_compare.
check(
    'double-digit minors compare as versions, not as strings',
    HRB_Updater::cache_ttl_for('1.10.0', '1.9.0'),
    $long
);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
