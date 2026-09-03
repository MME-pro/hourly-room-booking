<?php
/**
 * Updater Class
 *
 * Hooks the plugin into the native WordPress update system and serves
 * updates from GitHub releases instead of the WordPress.org repository.
 *
 * A release is picked up when its tag is a version greater than HRB_VERSION.
 * Tags may be written as "1.4.1" or "v1.4.1"; the leading "v" is stripped.
 * If the release carries a .zip asset that asset is used (it ships vendor/
 * and a clean folder name); otherwise the auto-generated source zipball is
 * used and the extracted directory is renamed to match the installed one.
 *
 * Private repositories are supported by defining HRB_GITHUB_TOKEN in
 * wp-config.php with a token that has "Contents: read" on the repository.
 *
 * @package HourlyRoomBooking
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Updater {

    /**
     * Class instance
     *
     * @var HRB_Updater|null
     */
    private static $instance = null;

    /**
     * GitHub repository owner
     */
    const GITHUB_OWNER = 'MME-pro';

    /**
     * GitHub repository name
     */
    const GITHUB_REPO = 'hourly-room-booking';

    /**
     * Transient key holding the cached release payload
     */
    const CACHE_KEY = 'hrb_github_release';

    /**
     * How long a release lookup is cached, in seconds (6 hours)
     */
    const CACHE_TTL = 21600;

    /**
     * Plugin basename, e.g. "hourly-room-booking-main/hourly-room-booking.php"
     *
     * @var string
     */
    private $basename;

    /**
     * Installed directory name, used as the update slug
     *
     * @var string
     */
    private $slug;

    /**
     * Get class instance
     *
     * @return HRB_Updater
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - registers the update hooks
     */
    private function __construct() {
        $this->basename = HRB_PLUGIN_BASENAME;
        $this->slug     = dirname(HRB_PLUGIN_BASENAME);

        // The update transient is also built during cron and WP-CLI runs,
        // so we cannot limit ourselves to is_admin().
        if (!is_admin() && !wp_doing_cron() && !(defined('WP_CLI') && WP_CLI)) {
            return;
        }

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_source_dir'], 10, 4);
        add_filter('upgrader_pre_download', [$this, 'authorize_download'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'purge_cache'], 10, 2);
        add_filter('plugin_row_meta', [$this, 'add_check_link'], 10, 2);
        add_action('admin_init', [$this, 'handle_force_check']);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Inject the GitHub release into the plugin update transient
     *
     * @param object $transient Update transient built by WordPress
     * @return object
     */
    public function check_for_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $release = $this->get_remote_release();
        if (empty($release['version'])) {
            return $transient;
        }

        $item = (object) [
            'id'            => 'github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'slug'          => $this->slug,
            'plugin'        => $this->basename,
            'new_version'   => $release['version'],
            'url'           => $release['homepage'],
            'package'       => $release['package'],
            'icons'         => [],
            'banners'       => [],
            'banners_rtl'   => [],
            'tested'        => $release['tested'],
            'requires'      => $release['requires'],
            'requires_php'  => $release['requires_php'],
            'compatibility' => new stdClass(),
        ];

        if (version_compare($release['version'], HRB_VERSION, '>')) {
            $transient->response[$this->basename] = $item;
            unset($transient->no_update[$this->basename]);
        } else {
            // Required for the auto-update toggle and "View details" link
            // to render on the plugins screen when we are up to date.
            $transient->no_update[$this->basename] = $item;
            unset($transient->response[$this->basename]);
        }

        return $transient;
    }

    /**
     * Supply the plugin details modal ("View version x.y.z details")
     *
     * @param false|object|array $result The result object or array
     * @param string             $action The API action being performed
     * @param object             $args   Plugin API arguments
     * @return false|object|array
     */
    public function plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->get_remote_release();
        if (empty($release['version'])) {
            return $result;
        }

        return (object) [
            'name'          => 'Hourly Room Booking System',
            'slug'          => $this->slug,
            'version'       => $release['version'],
            'author'        => '<a href="https://mme-pro.de">MME-Pro Dev Team</a>',
            'homepage'      => $release['homepage'],
            'requires'      => $release['requires'],
            'requires_php'  => $release['requires_php'],
            'tested'        => $release['tested'],
            'last_updated'  => $release['published_at'],
            'download_link' => $release['package'],
            'trunk'         => $release['package'],
            'sections'      => [
                'description' => $this->markdown_to_html($release['body']),
                'changelog'   => $this->markdown_to_html($release['body']),
            ],
        ];
    }

    /**
     * Rename the extracted archive folder to the installed plugin folder
     *
     * GitHub source zipballs extract to "owner-repo-<sha>" and release assets
     * may carry a version suffix. WordPress replaces the directory verbatim,
     * so without this the update would deactivate the plugin and leave a
     * duplicate copy behind.
     *
     * @param string      $source        Path to the extracted archive
     * @param string      $remote_source Path to the downloaded archive
     * @param WP_Upgrader $upgrader      Upgrader instance
     * @param array       $extra         Extra arguments (contains "plugin")
     * @return string|WP_Error
     */
    public function fix_source_dir($source, $remote_source, $upgrader, $extra = []) {
        if (empty($extra['plugin']) || $extra['plugin'] !== $this->basename) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $source;
        }

        $desired = trailingslashit($remote_source) . $this->slug;

        if (untrailingslashit($source) === $desired) {
            return $source;
        }

        if (!$wp_filesystem->move($source, $desired, true)) {
            return new WP_Error(
                'hrb_rename_failed',
                __('Could not rename the downloaded update folder.', 'hourly-room-booking')
            );
        }

        return trailingslashit($desired);
    }

    /**
     * Download the package ourselves when an access token is configured
     *
     * WordPress' own download_url() cannot send the Authorization header a
     * private repository requires, so we fetch the file here and hand the
     * temporary path back to the upgrader.
     *
     * @param bool        $reply    Whether to short-circuit the download
     * @param string      $package  Package URL
     * @param WP_Upgrader $upgrader Upgrader instance
     * @return bool|string|WP_Error
     */
    public function authorize_download($reply, $package, $upgrader) {
        $token = $this->get_token();

        if (false !== $reply || empty($token) || !is_string($package)) {
            return $reply;
        }

        $api_prefix = 'https://api.github.com/repos/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO;
        if (0 !== strpos($package, $api_prefix)) {
            return $reply;
        }

        $tmp = wp_tempnam($package);
        if (!$tmp) {
            return new WP_Error(
                'hrb_no_temp_file',
                __('Could not create a temporary file for the update.', 'hourly-room-booking')
            );
        }

        $response = wp_remote_get($package, [
            'timeout'  => 60,
            'stream'   => true,
            'filename' => $tmp,
            'headers'  => [
                'Accept'               => 'application/octet-stream',
                'Authorization'        => 'Bearer ' . $token,
                'User-Agent'           => 'HourlyRoomBooking/' . HRB_VERSION,
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
        ]);

        if (is_wp_error($response)) {
            unlink($tmp);
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            unlink($tmp);
            return new WP_Error(
                'hrb_download_failed',
                sprintf(
                    /* translators: %d: HTTP status code returned by GitHub */
                    __('GitHub returned HTTP %d while downloading the update.', 'hourly-room-booking'),
                    $code
                )
            );
        }

        return $tmp;
    }

    /**
     * Drop the cached release after an update ran
     *
     * @param WP_Upgrader $upgrader Upgrader instance
     * @param array       $options  Update options
     */
    public function purge_cache($upgrader, $options = []) {
        if (empty($options['action']) || 'update' !== $options['action']) {
            return;
        }

        if (empty($options['type']) || 'plugin' !== $options['type']) {
            return;
        }

        $plugins = isset($options['plugins']) ? (array) $options['plugins'] : [];
        if (!empty($options['plugin'])) {
            $plugins[] = $options['plugin'];
        }

        if (in_array($this->basename, $plugins, true)) {
            delete_transient(self::CACHE_KEY);
        }
    }

    /**
     * Add a "Check for updates" link to the plugin row
     *
     * @param array  $links Existing row meta links
     * @param string $file  Plugin file the row belongs to
     * @return array
     */
    public function add_check_link($links, $file) {
        if ($file !== $this->basename || !current_user_can('update_plugins')) {
            return $links;
        }

        $url = wp_nonce_url(
            add_query_arg('hrb-force-check', '1', self_admin_url('plugins.php')),
            'hrb_force_check'
        );

        $links[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('Check for updates', 'hourly-room-booking')
        );

        return $links;
    }

    /**
     * Handle the manual "Check for updates" request
     */
    public function handle_force_check() {
        if (empty($_GET['hrb-force-check']) || !current_user_can('update_plugins')) {
            return;
        }

        check_admin_referer('hrb_force_check');

        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        wp_update_plugins();

        wp_safe_redirect(self_admin_url('plugins.php'));
        exit;
    }

    /**
     * Fetch the latest GitHub release, cached in a transient
     *
     * @return array Normalised release data; "version" is empty on failure
     */
    private function get_remote_release() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $empty = [
            'version'      => '',
            'package'      => '',
            'homepage'     => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'body'         => '',
            'published_at' => '',
            'tested'       => '',
            'requires'     => '',
            'requires_php' => '',
        ];

        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'User-Agent'           => 'HourlyRoomBooking/' . HRB_VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $token = $this->get_token();
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/releases/latest',
            ['timeout' => 15, 'headers' => $headers]
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            // Cache the miss briefly so a broken API does not slow every
            // admin page load down with a fresh 15 second timeout.
            set_transient(self::CACHE_KEY, $empty, 15 * MINUTE_IN_SECONDS);
            return $empty;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($release) || empty($release['tag_name']) || !empty($release['draft'])) {
            set_transient(self::CACHE_KEY, $empty, 15 * MINUTE_IN_SECONDS);
            return $empty;
        }

        $header_data = $this->get_header_data();

        $data = [
            'version'      => ltrim((string) $release['tag_name'], 'vV'),
            'package'      => $this->pick_package($release),
            'homepage'     => !empty($release['html_url']) ? $release['html_url'] : $empty['homepage'],
            'body'         => isset($release['body']) ? (string) $release['body'] : '',
            'published_at' => isset($release['published_at']) ? (string) $release['published_at'] : '',
            'tested'       => $header_data['tested'],
            'requires'     => $header_data['requires'],
            'requires_php' => $header_data['requires_php'],
        ];

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }

    /**
     * Choose the download URL for a release
     *
     * Prefers an uploaded .zip asset over the auto-generated source zipball,
     * because the asset is a build that already contains vendor/.
     *
     * @param array $release Decoded release payload
     * @return string
     */
    private function pick_package(array $release) {
        $token = $this->get_token();

        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (empty($asset['name']) || '.zip' !== substr($asset['name'], -4)) {
                    continue;
                }

                // The API asset URL is the only one that accepts a token, so
                // private repositories must go through it.
                if (!empty($token) && !empty($asset['url'])) {
                    return $asset['url'];
                }

                if (!empty($asset['browser_download_url'])) {
                    return $asset['browser_download_url'];
                }
            }
        }

        return !empty($release['zipball_url']) ? $release['zipball_url'] : '';
    }

    /**
     * Read compatibility headers straight from the plugin file
     *
     * @return array
     */
    private function get_header_data() {
        $data = get_file_data(HRB_PLUGIN_FILE, [
            'tested'       => 'Tested up to',
            'requires'     => 'Requires at least',
            'requires_php' => 'Requires PHP',
        ]);

        return [
            'tested'       => isset($data['tested']) ? $data['tested'] : '',
            'requires'     => isset($data['requires']) ? $data['requires'] : '',
            'requires_php' => isset($data['requires_php']) ? $data['requires_php'] : '',
        ];
    }

    /**
     * Get the configured GitHub access token, if any
     *
     * @return string
     */
    private function get_token() {
        $token = defined('HRB_GITHUB_TOKEN') ? HRB_GITHUB_TOKEN : '';

        /**
         * Filter the GitHub token used for release lookups and downloads.
         *
         * @param string $token
         */
        return (string) apply_filters('hrb_github_token', $token);
    }

    /**
     * Render the release notes for the details modal
     *
     * Handles the small subset of Markdown that release notes actually use;
     * anything else is escaped and shown as written.
     *
     * @param string $markdown Release body
     * @return string
     */
    private function markdown_to_html($markdown) {
        if ('' === trim((string) $markdown)) {
            return '<p>' . esc_html__('No release notes were provided.', 'hourly-room-booking') . '</p>';
        }

        $html = esc_html($markdown);

        // Headings (### Title)
        $html = preg_replace('/^#{1,6}\s*(.+)$/m', '<h4>$1</h4>', $html);

        // Bullets (- item / * item)
        $html = preg_replace('/^[\*\-]\s+(.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(?:<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html);

        // Bold and inline code
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        return wpautop($html);
    }
}
