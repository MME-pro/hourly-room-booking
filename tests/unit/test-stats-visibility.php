<?php
/**
 * Tests for who is shown a screen's stats header.
 *
 * The client's Admin runs the whole business - bookings, payments, reports,
 * settings, exports - and is still not shown the headline totals above the
 * Payments screen. Those are ours. The capability drawing that line is
 * hrb_view_stats, and the thing most likely to quietly undo it is the grant to
 * WordPress administrators, because on a client site the client usually is
 * one: a blanket all_caps() grant there hands the headers straight back.
 *
 * The other trap is the upgrade. Before 1.18.0 every administrator and every
 * Room Booking Admin had the full set written onto their *user* record, and a
 * capability on the user outranks the role, so taking it off the role alone
 * changes nothing for anyone who already exists.
 *
 * Standalone, no PHPUnit:
 *
 *     php tests/unit/test-stats-visibility.php
 *
 * @package HourlyRoomBooking
 * @since 1.18.0
 */

define('ABSPATH', __DIR__);

// ---------------------------------------------------------------------------
// Just enough of WordPress's roles and users to run add_user_roles()
// ---------------------------------------------------------------------------

class FakeRole {
    public $name;
    public $capabilities = [];

    public function __construct($name, $caps = []) {
        $this->name = $name;
        $this->capabilities = $caps;
    }

    public function add_cap($cap) { $this->capabilities[$cap] = true; }
    public function remove_cap($cap) { unset($this->capabilities[$cap]); }
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}

class FakeUser {
    public $ID;
    public $roles = [];
    public $caps = [];   // written straight onto the user

    public function __construct($id, array $roles, array $caps = []) {
        $this->ID = $id;
        $this->roles = $roles;
        $this->caps = $caps;
    }

    public function add_cap($cap) { $this->caps[$cap] = true; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }

    /** A capability on the user wins; otherwise the role decides. */
    public function has_cap($cap) {
        if (array_key_exists($cap, $this->caps)) {
            return (bool) $this->caps[$cap];
        }

        foreach ($this->roles as $role) {
            if (!empty($GLOBALS['fake_roles'][$role]) && $GLOBALS['fake_roles'][$role]->has_cap($cap)) {
                return true;
            }
        }

        return false;
    }
}

$GLOBALS['fake_roles'] = ['administrator' => new FakeRole('administrator', ['manage_options' => true])];
$GLOBALS['fake_users'] = [];

function add_role($slug, $label, $caps) { $GLOBALS['fake_roles'][$slug] = new FakeRole($label, $caps); }
function remove_role($slug) { unset($GLOBALS['fake_roles'][$slug]); }
function get_role($slug) { return $GLOBALS['fake_roles'][$slug] ?? null; }

function get_users($args) {
    return array_values(array_filter(
        $GLOBALS['fake_users'],
        function ($u) use ($args) { return in_array($args['role'], $u->roles, true); }
    ));
}

function add_action() { return true; }
function add_filter() { return true; }
function __($text, $domain = null) { return $text; }

require_once dirname(__DIR__, 2) . '/includes/class-capabilities.php';
require_once dirname(__DIR__, 2) . '/includes/class-admin.php';

$failures = 0;

function check(string $label, $actual, $expected): void {
    global $failures;

    $passed = $actual === $expected;
    if (!$passed) {
        $failures++;
    }

    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);

    if (!$passed) {
        printf("    expected: %s\n    actual:   %s\n", var_export($expected, true), var_export($actual, true));
    }
}

$stats = HRB_Capabilities::STATS;

// A site as it stands just before the upgrade: everyone who was an Admin of
// any kind had the whole set written onto their user record, stats included.
$everything = array_fill_keys(HRB_Capabilities::all_caps(), true);

$GLOBALS['fake_users'] = [
    'wp_admin'    => new FakeUser(1, ['administrator'], $everything),
    'client'      => new FakeUser(2, [HRB_Capabilities::ROLE_ADMIN], $everything),
    'employee'    => new FakeUser(3, [HRB_Capabilities::ROLE_EMPLOYEE], $everything),
    'us'          => new FakeUser(4, [HRB_Capabilities::ROLE_SUPER_ADMIN]),
    'us_wp_admin' => new FakeUser(5, [HRB_Capabilities::ROLE_SUPER_ADMIN, 'administrator'], $everything),
];

HRB_Admin::add_user_roles();

$users = $GLOBALS['fake_users'];

// ---------------------------------------------------------------------------
// The three roles exist and carry what they should
// ---------------------------------------------------------------------------

echo "\n-- the roles --\n";

check('a Super Admin role is registered', get_role(HRB_Capabilities::ROLE_SUPER_ADMIN) !== null, true);
check('an Admin role is registered', get_role(HRB_Capabilities::ROLE_ADMIN) !== null, true);
check('an Employee role is registered', get_role(HRB_Capabilities::ROLE_EMPLOYEE) !== null, true);

check('the Super Admin role carries the stats headers',
    get_role(HRB_Capabilities::ROLE_SUPER_ADMIN)->has_cap($stats), true);
check('the Admin role does not',
    get_role(HRB_Capabilities::ROLE_ADMIN)->has_cap($stats), false);
check('nor does the Employee role',
    get_role(HRB_Capabilities::ROLE_EMPLOYEE)->has_cap($stats), false);

// ---------------------------------------------------------------------------
// The grant that would quietly undo the whole thing
// ---------------------------------------------------------------------------

echo "\n-- and the WordPress administrator role --\n";

check('still runs the plugin', get_role('administrator')->has_cap('hrb_manage_settings'), true);
check('still sees the money', get_role('administrator')->has_cap(HRB_Capabilities::FINANCIALS), true);
check('but is not given the stats headers', get_role('administrator')->has_cap($stats), false);
check('and keeps its own WordPress capabilities', get_role('administrator')->has_cap('manage_options'), true);

// 'read' is WordPress's, and deactivation only takes back what all_caps()
// names - so granting it here would leave it behind for good.
check('and is not handed "read" by this plugin',
    array_key_exists('read', get_role('administrator')->capabilities), false);
check('nor is it written onto an administrator',
    array_key_exists('read', $users['wp_admin']->caps), false);

// ---------------------------------------------------------------------------
// What each existing user ends up seeing
// ---------------------------------------------------------------------------

echo "\n-- upgrading a site that already had users --\n";

check('the client, a WordPress administrator, loses the headers', $users['wp_admin']->has_cap($stats), false);
check('...and loses nothing else', $users['wp_admin']->has_cap(HRB_Capabilities::FINANCIALS), true);
check('...still exports', $users['wp_admin']->has_cap('hrb_export_data'), true);
check('...still works payments', $users['wp_admin']->has_cap('hrb_manage_payments'), true);

check('the client on the Admin role loses the headers', $users['client']->has_cap($stats), false);
check('...and keeps the reports', $users['client']->has_cap('hrb_view_reports'), true);
check('...and the settings', $users['client']->has_cap('hrb_manage_settings'), true);

check('an Employee never had them', $users['employee']->has_cap($stats), false);
check('...and still has no reports', $users['employee']->has_cap('hrb_view_reports'), false);
check('...but still runs the desk', $users['employee']->has_cap('hrb_manage_bookings'), true);

check('we keep them', $users['us']->has_cap($stats), true);
check('...along with everything else', $users['us']->has_cap(HRB_Capabilities::FINANCIALS), true);

// The case the revoke loop has to step around: one of us who is also a
// WordPress administrator. Stripping every administrator would take the
// headers off the only people meant to have them.
check('and we keep them while also being a WordPress administrator',
    $users['us_wp_admin']->has_cap($stats), true);

// ---------------------------------------------------------------------------
// Running it twice must not drift
// ---------------------------------------------------------------------------

echo "\n-- and again, because it runs on every admin load --\n";

HRB_Admin::add_user_roles();

check('the Super Admin still has them', $users['us']->has_cap($stats), true);
check('the client still does not', $users['client']->has_cap($stats), false);
check('the WordPress administrator still does not', $users['wp_admin']->has_cap($stats), false);
check('the Admin role is still intact', get_role(HRB_Capabilities::ROLE_ADMIN)->has_cap('hrb_view_reports'), true);

// ---------------------------------------------------------------------------
// Deactivation takes all three away
// ---------------------------------------------------------------------------

echo "\n-- on the way out --\n";

HRB_Admin::remove_user_roles();

check('the Super Admin role is removed', get_role(HRB_Capabilities::ROLE_SUPER_ADMIN), null);
check('the Admin role is removed', get_role(HRB_Capabilities::ROLE_ADMIN), null);
check('the Employee role is removed', get_role(HRB_Capabilities::ROLE_EMPLOYEE), null);
check('and the administrator role is handed back clean',
    array_values(array_intersect(array_keys(get_role('administrator')->capabilities), HRB_Capabilities::all_caps())), []);

echo "\n";
echo $failures === 0 ? "ALL PASSED\n" : sprintf("%d FAILED\n", $failures);
exit($failures === 0 ? 0 : 1);
