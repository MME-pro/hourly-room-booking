<?php
/**
 * Tests for the admin body classes and asset cache-busting.
 *
 * The full-bleed screens (coloured header, own background) have to sit flush
 * against the admin menu, which means dropping the 20px gutter WordPress puts
 * on #wpcontent. That is done per page via body.hrb-fullbleed-page, so the
 * ordinary .wrap screens keep the standard spacing — this pins down exactly
 * which pages get it.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-admin-body-class.php
 *
 * @package HourlyRoomBooking
 * @since 1.7.1
 */

define('ABSPATH', __DIR__);
define('HRB_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
define('HRB_VERSION', '1.7.1');

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

// class-admin.php is far too entangled to load here; the body-class rule is
// short enough to mirror exactly, and the assertion below keeps the copy
// honest by diffing it against the real source.
function body_classes(string $page): string {
    $_GET['page'] = $page;

    $classes = '';

    if (strpos($page, 'hrb') !== 0) {
        return $classes;
    }

    $classes .= ' hrb-admin-page';

    $full_bleed = [
        'hrb-rooms',
        'hrb-room-availability',
        'hrb-calendar',
        'hrb-customers',
        'hrb-extras',
        'hrb-extras-availability',
        'hrb-payments',
        'hrb-reports',
    ];

    if (in_array($page, $full_bleed, true)) {
        $classes .= ' hrb-fullbleed-page';
    }

    return $classes;
}

function has_class(string $page, string $class): bool {
    return in_array($class, preg_split('/\s+/', trim(body_classes($page))), true);
}

// ---------------------------------------------------------------------------
// The list in this test must match the one in the plugin
// ---------------------------------------------------------------------------

echo "\n-- the copy stays in step with the source --\n";

$source = file_get_contents(HRB_PLUGIN_DIR . 'includes/class-admin.php');
preg_match('/\$full_bleed = \[(.*?)\];/s', $source, $m);
preg_match_all("/'([a-z-]+)'/", $m[1] ?? '', $source_pages);

$mirrored = [
    'hrb-rooms', 'hrb-room-availability', 'hrb-calendar', 'hrb-customers',
    'hrb-extras', 'hrb-extras-availability', 'hrb-payments', 'hrb-reports',
];

check('the plugin declares the same full-bleed pages', $source_pages[1] ?? [], $mirrored);

// ---------------------------------------------------------------------------
// Full-bleed screens: flush against the menu
// ---------------------------------------------------------------------------

echo "\n-- full-bleed screens --\n";

foreach ($mirrored as $page) {
    check("{$page} is full-bleed", has_class($page, 'hrb-fullbleed-page'), true);
    check("{$page} is still a plugin page", has_class($page, 'hrb-admin-page'), true);
}

// ---------------------------------------------------------------------------
// .wrap screens: keep WordPress' standard gutter
// ---------------------------------------------------------------------------

echo "\n-- ordinary .wrap screens keep the gutter --\n";

foreach (['hrb-bookings', 'hrb-old-bookings', 'hrb-dashboard', 'hrb-settings', 'hrb-email-templates', 'hrb-guide'] as $page) {
    check("{$page} is NOT full-bleed", has_class($page, 'hrb-fullbleed-page'), false);
    check("{$page} is still a plugin page", has_class($page, 'hrb-admin-page'), true);
}

// ---------------------------------------------------------------------------
// Other admin screens are untouched
// ---------------------------------------------------------------------------

echo "\n-- non-plugin screens --\n";

foreach (['edit.php', 'options-general', 'plugins', 'woocommerce'] as $page) {
    check("{$page} gets no plugin classes", body_classes($page), '');
}

// A page that merely contains "hrb" later in the slug is not ours.
check('a slug that only contains hrb is not matched', body_classes('other-hrb-thing'), '');

// ---------------------------------------------------------------------------
// The stylesheet actually carries the rule the class exists for
// ---------------------------------------------------------------------------

echo "\n-- the stylesheet rule --\n";

$css = file_get_contents(HRB_PLUGIN_DIR . 'admin/assets/css/admin.css');

check(
    'the gutter is dropped for full-bleed pages',
    (bool) preg_match('/body\.hrb-fullbleed-page\s+#wpcontent\s*\{[^}]*padding-left:\s*0/', $css),
    true
);

// The page runs flush to the admin menu because the gutter is removed outside
// it (#wpcontent), not by stripping the wrapper's padding — the content inside
// keeps its comfortable inset. Measured in a headless browser at 1600px:
// menu ends at 160, page background starts at 160, content starts at 184.
check(
    'the wrapper is no longer stripped of its left padding',
    (bool) preg_match('/body\.hrb-admin-page\s+\.hrb-admin-page\s*\{[^}]*padding-left:\s*0/', $css),
    false
);

check(
    'the wrapper margins are forced to zero instead',
    (bool) preg_match('/body\.hrb-admin-page\s+\.hrb-admin-page\s*\{[^}]*margin-left:\s*0\s*!important/', $css),
    true
);

// The views set margin: -10px at phone widths, which only worked while <body>
// carried the 24px padding; it would now pull content off the left edge.
check(
    'no negative wrapper margin survives at phone widths',
    (bool) preg_match('/@media[^{]*768px[^{]*\{[^}]*\.hrb-admin-page\s*\{[^}]*margin:\s*-/s', $css),
    false
);

// The earlier attempt pulled the wrapper over the gutter with a negative
// margin; that is gone in favour of removing the gutter itself.
check(
    'no leftover negative-margin workaround',
    strpos($css, 'margin-left: -20px') === false,
    true
);

// The class on <body> is the same name the views use for their wrapper div, so
// every view's ".hrb-admin-page { padding: 24px }" also lands on <body> and
// pads the whole admin — menu included — in from the viewport. Measured in a
// headless browser: it moved the admin menu's right edge from 160px to 184px.
// The descendant rule above cannot undo it (body is not a descendant of
// itself), so the box has to be reset on the body itself.
check(
    'the body box is reset so the wrapper styling cannot pad the whole admin',
    (bool) preg_match('/body\.hrb-admin-page\s*\{[^}]*padding:\s*0\s*!important[^}]*\}/', $css),
    true
);

check(
    '...including the margin (the views set a negative one at mobile widths)',
    (bool) preg_match('/body\.hrb-admin-page\s*\{[^}]*margin:\s*0\s*!important[^}]*\}/', $css),
    true
);

// Guard the cause, not just the symptom: the views really do style the class
// as a block-level wrapper, which is why the body needs the reset.
$views_padding = 0;
foreach (glob(HRB_PLUGIN_DIR . 'admin/views/*.php') as $view) {
    if (preg_match('/^\s*\.hrb-admin-page\s*\{[^}]*padding:/m', file_get_contents($view))) {
        $views_padding++;
    }
}

check('views still style .hrb-admin-page with padding', $views_padding > 0, true);

// ---------------------------------------------------------------------------
// Asset cache-busting
// ---------------------------------------------------------------------------

echo "\n-- asset_version --\n";

function asset_version($relative_path) {
    $file = HRB_PLUGIN_DIR . ltrim($relative_path, '/');

    if (file_exists($file)) {
        $modified = filemtime($file);

        if ($modified) {
            return HRB_VERSION . '.' . $modified;
        }
    }

    return HRB_VERSION;
}

$version = asset_version('admin/assets/css/admin.css');

check('an existing asset is stamped with its mtime', $version !== HRB_VERSION, true);
check('...and still starts with the plugin version', strpos($version, HRB_VERSION . '.') === 0, true);
check('a missing asset falls back to the plugin version', asset_version('admin/assets/css/nope.css'), HRB_VERSION);

// The bootstrap enqueues the "hrb-admin" handle before HRB_Admin does, so its
// version is the one the browser actually sees. Stamping only HRB_Admin's copy
// leaves the browser on a cached stylesheet until HRB_VERSION changes — which
// is exactly how a CSS fix can look like it did nothing.
echo "\n-- both enqueues must bust the cache --\n";

$bootstrap = file_get_contents(HRB_PLUGIN_DIR . 'hourly-room-booking.php');

check(
    'the bootstrap stamps admin.css with the file version',
    (bool) preg_match('/asset_version\(\s*[\'"]admin\/assets\/css\/admin\.css[\'"]\s*\)/', $bootstrap),
    true
);

check(
    'the bootstrap stamps admin.js with the file version',
    (bool) preg_match('/asset_version\(\s*[\'"]admin\/assets\/js\/admin\.js[\'"]\s*\)/', $bootstrap),
    true
);

check(
    'no admin asset is still pinned to the bare plugin version',
    (bool) preg_match("/admin\/assets\/(css|js)\/admin\.(css|js)'[^)]*?,\s*HRB_VERSION\s*[,)]/s", $bootstrap),
    false
);

$admin_source = file_get_contents(HRB_PLUGIN_DIR . 'includes/class-admin.php');

check(
    'HRB_Admin stamps its own enqueue too',
    substr_count($admin_source, "self::asset_version('admin/assets/") >= 2,
    true
);

echo "\n" . (0 === $failures ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");

exit(0 === $failures ? 0 : 1);
