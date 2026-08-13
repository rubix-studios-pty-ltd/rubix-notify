<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Database
{
    private const DB_VERSION = '1.2.0';
    private const DB_VERSION_OPTION = 'rubix_notify_db_version';

    private const SETTING_KEY = 'default';

    private const EVENT_POST_PUBLISHED = 'post_published';
    private const EVENT_LOGIN_SUCCESS = 'login_success';
    private const EVENT_LOGIN_FAILURE = 'login_failure';

    private const CACHE_GROUP = 'ntfy_alerts';

    private const CACHE_KEY_SETTINGS = 'settings_default';
    private const CACHE_KEY_POST = 'post_default';
    private const CACHE_KEY_TEMPLATES = 'templates_default';

    private static function clear_cache(): void
    {
        wp_cache_delete(self::CACHE_KEY_SETTINGS, self::CACHE_GROUP);
        wp_cache_delete(self::CACHE_KEY_POST, self::CACHE_GROUP);
        wp_cache_delete(self::CACHE_KEY_TEMPLATES, self::CACHE_GROUP);
    }

    public static function table(): string
    {
        return self::settings_table();
    }

    public static function settings_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'rubix_notify_settings';
    }

    public static function post_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'rubix_notify_post';
    }

    public static function templates_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'rubix_notify_templates';
    }

    public static function login_attempts_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'rubix_notify_login_attempts';
    }

    public static function login_ips_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'rubix_notify_login_ips';
    }

    public static function activate(): void
    {
        global $wpdb;

        $installed_version = (string) get_option(self::DB_VERSION_OPTION, '');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $settings_table = self::settings_table();

        $settings_sql = "CREATE TABLE {$settings_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(64) NOT NULL DEFAULT 'default',
            server_url_encrypted LONGTEXT NULL,
            auth_token_encrypted LONGTEXT NULL,
            include_user_agent TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) {$charset};";

        $post_table = self::post_table();

        $post_sql = "CREATE TABLE {$post_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(64) NOT NULL DEFAULT 'default',
            event_key VARCHAR(64) NOT NULL DEFAULT 'post_published',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            rule_type VARCHAR(32) NOT NULL DEFAULT 'all',
            post_type VARCHAR(32) NOT NULL DEFAULT 'post',
            taxonomy VARCHAR(32) NOT NULL DEFAULT 'category',
            term_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            topic_encrypted LONGTEXT NULL,
            include_children TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY notification_rule (
                setting_key,
                event_key,
                rule_type,
                post_type,
                taxonomy,
                term_id
            ),
            KEY term_lookup (taxonomy, term_id),
            KEY post_type_lookup (post_type),
            KEY enabled_lookup (enabled)
        ) {$charset};";

        $templates_table = self::templates_table();

        $templates_sql = "CREATE TABLE {$templates_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(64) NOT NULL DEFAULT 'default',
            event_key VARCHAR(64) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            topic_encrypted LONGTEXT NULL,
            title_encrypted LONGTEXT NULL,
            message_encrypted LONGTEXT NULL,
            priority VARCHAR(16) NOT NULL DEFAULT 'default',
            tags VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY setting_event (setting_key, event_key)
        ) {$charset};";

        $login_attempts_table = self::login_attempts_table();

        $login_attempts_sql = "CREATE TABLE {$login_attempts_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            username VARCHAR(191) NOT NULL DEFAULT '',
            error_code VARCHAR(64) NOT NULL DEFAULT '',
            attempted_at_gmt DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY ip_attempted (ip_address, attempted_at_gmt),
            KEY attempted_at_gmt (attempted_at_gmt)
        ) {$charset};";

        $login_ips_table = self::login_ips_table();

        $login_ips_sql = "CREATE TABLE {$login_ips_table} (
            ip_address VARCHAR(45) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'observed',
            total_failures BIGINT UNSIGNED NOT NULL DEFAULT 0,
            first_failed_at_gmt DATETIME DEFAULT NULL,
            last_failed_at_gmt DATETIME DEFAULT NULL,
            last_notified_at_gmt DATETIME DEFAULT NULL,
            rule_changed_at_gmt DATETIME DEFAULT NULL,
            rule_changed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at_gmt DATETIME NOT NULL,
            updated_at_gmt DATETIME NOT NULL,
            PRIMARY KEY  (ip_address),
            KEY status (status),
            KEY last_failed_at_gmt (last_failed_at_gmt)
        ) {$charset};";

        dbDelta($settings_sql);
        dbDelta($post_sql);
        dbDelta($templates_sql);
        dbDelta($login_attempts_sql);
        dbDelta($login_ips_sql);

        self::insert_default_rows();
        self::upgrade_failure_template($installed_version);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option(self::DB_VERSION_OPTION, '');

        if ($installed === self::DB_VERSION) {
            return;
        }

        self::activate();
    }

    public static function record_login_failure(
        string $ip_address,
        string $username,
        string $error_code
    ): array {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $username = sanitize_text_field(wp_unslash($username));
        $username = function_exists('mb_substr')
            ? mb_substr($username, 0, 191)
            : substr($username, 0, 191);
        $error_code = substr(sanitize_key($error_code), 0, 64);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Security audit data is stored in a custom table.
        $wpdb->insert(
            self::login_attempts_table(),
            [
                'ip_address' => $ip_address,
                'username' => $username,
                'error_code' => $error_code,
                'attempted_at_gmt' => $now,
            ],
            ['%s', '%s', '%s', '%s']
        );

        if ($ip_address === '') {
            return [
                'failures_last_hour' => 0,
                'status' => 'observed',
            ];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic aggregate update in a custom table.
        $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %i
                    (ip_address, status, total_failures, first_failed_at_gmt, last_failed_at_gmt, created_at_gmt, updated_at_gmt)
                VALUES (%s, %s, 1, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    total_failures = total_failures + 1,
                    first_failed_at_gmt = COALESCE(first_failed_at_gmt, VALUES(first_failed_at_gmt)),
                    last_failed_at_gmt = VALUES(last_failed_at_gmt),
                    updated_at_gmt = VALUES(updated_at_gmt)',
                self::login_ips_table(),
                $ip_address,
                'observed',
                $now,
                $now,
                $now,
                $now
            )
        );

        $cutoff = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Rolling security count in a custom table.
        $failures_last_hour = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE ip_address = %s AND attempted_at_gmt >= %s',
                self::login_attempts_table(),
                $ip_address,
                $cutoff
            )
        );

        return [
            'failures_last_hour' => $failures_last_hour,
            'status' => self::get_login_ip_status($ip_address),
        ];
    }

    public static function get_login_ip_status(string $ip_address): string
    {
        global $wpdb;

        if ($ip_address === '') {
            return 'observed';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Security rule lookup in a custom table.
        $status = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT status FROM %i WHERE ip_address = %s',
                self::login_ips_table(),
                $ip_address
            )
        );

        return in_array($status, ['banned', 'whitelisted'], true)
            ? (string) $status
            : 'observed';
    }

    public static function set_login_ip_status(
        string $ip_address,
        string $status,
        int $user_id
    ): bool {
        global $wpdb;

        if (!in_array($status, ['observed', 'banned', 'whitelisted'], true)) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');

        if ($status === 'observed') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Security rule update in a custom table.
            $result = $wpdb->update(
                self::login_ips_table(),
                [
                    'status' => 'observed',
                    'rule_changed_at_gmt' => $now,
                    'rule_changed_by' => $user_id,
                    'updated_at_gmt' => $now,
                ],
                ['ip_address' => $ip_address],
                ['%s', '%s', '%d', '%s'],
                ['%s']
            );

            return $result !== false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic security rule insert or update in a custom table.
        $result = $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO %i
                    (ip_address, status, total_failures, rule_changed_at_gmt, rule_changed_by, created_at_gmt, updated_at_gmt)
                VALUES (%s, %s, 0, %s, %d, %s, %s)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    rule_changed_at_gmt = VALUES(rule_changed_at_gmt),
                    rule_changed_by = VALUES(rule_changed_by),
                    updated_at_gmt = VALUES(updated_at_gmt)',
                self::login_ips_table(),
                $ip_address,
                $status,
                $now,
                $user_id,
                $now,
                $now
            )
        );

        return $result !== false;
    }

    public static function claim_login_alert(
        string $ip_address,
        int $cooldown_seconds
    ): ?string {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $cooldown_seconds));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic notification throttle in a custom table.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i
                SET last_notified_at_gmt = %s, updated_at_gmt = %s
                WHERE ip_address = %s
                    AND status <> 'whitelisted'
                    AND (last_notified_at_gmt IS NULL OR last_notified_at_gmt < %s)",
                self::login_ips_table(),
                $now,
                $now,
                $ip_address,
                $cutoff
            )
        );

        return $updated === 1 ? $now : null;
    }

    public static function release_login_alert(string $ip_address, string $claimed_at_gmt): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Releases a failed notification claim for retry.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET last_notified_at_gmt = NULL WHERE ip_address = %s AND last_notified_at_gmt = %s',
                self::login_ips_table(),
                $ip_address,
                $claimed_at_gmt
            )
        );
    }

    public static function get_login_security_snapshot(int $limit = 100): array
    {
        global $wpdb;

        $limit = max(1, min(200, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admin security report from custom tables.
        $ips = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.ip_address, i.status, i.total_failures, i.first_failed_at_gmt,
                    i.last_failed_at_gmt, i.last_notified_at_gmt,
                    COALESCE(recent.failures_last_hour, 0) AS failures_last_hour
                FROM %i i
                LEFT JOIN (
                    SELECT ip_address, COUNT(*) AS failures_last_hour
                    FROM %i
                    WHERE attempted_at_gmt >= %s
                    GROUP BY ip_address
                ) recent ON recent.ip_address = i.ip_address
                ORDER BY
                    CASE
                        WHEN i.status = 'banned' THEN 0
                        WHEN i.status = 'whitelisted' THEN 1
                        ELSE 2
                    END,
                    COALESCE(i.last_failed_at_gmt, i.updated_at_gmt) DESC
                LIMIT %d",
                self::login_ips_table(),
                self::login_attempts_table(),
                $cutoff,
                $limit
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Recent audit report from a custom table.
        $attempts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, ip_address, username, error_code, attempted_at_gmt
                FROM %i
                ORDER BY attempted_at_gmt DESC, id DESC
                LIMIT %d',
                self::login_attempts_table(),
                50
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admin summary from custom security tables.
        $attempts_last_hour = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE attempted_at_gmt >= %s',
                self::login_attempts_table(),
                $cutoff
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admin summary from a custom security table.
        $status_rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT status, COUNT(*) AS total FROM %i GROUP BY status',
                self::login_ips_table()
            ),
            ARRAY_A
        );

        $summary = [
            'attempts_last_hour' => $attempts_last_hour,
            'tracked_ips' => 0,
            'banned_ips' => 0,
            'whitelisted_ips' => 0,
        ];

        foreach ((array) $status_rows as $row) {
            $count = (int) ($row['total'] ?? 0);
            $summary['tracked_ips'] += $count;

            if (($row['status'] ?? '') === 'banned') {
                $summary['banned_ips'] = $count;
            } elseif (($row['status'] ?? '') === 'whitelisted') {
                $summary['whitelisted_ips'] = $count;
            }
        }

        foreach ((array) $ips as &$ip) {
            $ip['total_failures'] = (int) ($ip['total_failures'] ?? 0);
            $ip['failures_last_hour'] = (int) ($ip['failures_last_hour'] ?? 0);
        }
        unset($ip);

        foreach ((array) $attempts as &$attempt) {
            $attempt['id'] = (int) ($attempt['id'] ?? 0);
        }
        unset($attempt);

        return [
            'summary' => $summary,
            'ips' => is_array($ips) ? $ips : [],
            'attempts' => is_array($attempts) ? $attempts : [],
        ];
    }

    public static function delete_old_login_attempts(int $retention_days = 90): int
    {
        global $wpdb;

        $cutoff = gmdate(
            'Y-m-d H:i:s',
            time() - (max(1, $retention_days) * DAY_IN_SECONDS)
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Scheduled retention cleanup for custom audit data.
        $deleted = $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE attempted_at_gmt < %s',
                self::login_attempts_table(),
                $cutoff
            )
        );

        return is_int($deleted) ? $deleted : 0;
    }

    public static function get_settings(): array
    {
        $defaults = self::defaults();

        $settings = self::get_settings_row();
        $post = self::get_post_rows();
        $templates = self::get_template_rows();

        if (!$settings && empty($post) && empty($templates)) {
            return $defaults;
        }

        $post_template = $templates[self::EVENT_POST_PUBLISHED] ?? [];
        $success = $templates[self::EVENT_LOGIN_SUCCESS] ?? [];
        $failure = $templates[self::EVENT_LOGIN_FAILURE] ?? [];

        return [
            'server_url' => self::decrypt_value(
                $settings['server_url_encrypted'] ?? '',
                $defaults['server_url']
            ),
            'auth_token' => self::decrypt_value($settings['auth_token_encrypted'] ?? ''),
            'include_user_agent' => (bool) ($settings['include_user_agent'] ?? false),
            'post' => self::format_post($post),
            'templates' => [
                self::EVENT_POST_PUBLISHED => self::format_template(
                    $post_template,
                    $defaults['templates'][self::EVENT_POST_PUBLISHED]
                ),
                self::EVENT_LOGIN_SUCCESS => self::format_template(
                    $success,
                    $defaults['templates'][self::EVENT_LOGIN_SUCCESS]
                ),
                self::EVENT_LOGIN_FAILURE => self::format_template(
                    $failure,
                    $defaults['templates'][self::EVENT_LOGIN_FAILURE]
                ),
            ],
        ];
    }

    public static function get_public_settings(): array
    {
        $settings = self::get_settings();
        $row = self::get_settings_row();

        unset($settings['auth_token']);

        $settings['has_auth_token'] = !empty($row['auth_token_encrypted'] ?? '');
        $settings['available_variables'] = Ntfy_Template::variables();

        return $settings;
    }

    public static function save_settings(array $input): void
    {
        global $wpdb;

        $existing = self::get_settings_row();

        $server_url = self::sanitize_server_url(self::value($input, 'server_url'));
        $auth_token = sanitize_text_field(wp_unslash(self::value($input, 'auth_token')));

        $auth_token_encrypted = $existing['auth_token_encrypted'] ?? '';

        if (!empty($input['clear_auth_token'])) {
            $auth_token_encrypted = '';
        } elseif ($auth_token !== '') {
            $auth_token_encrypted = Ntfy_Crypto::encrypt($auth_token);
        }

        $now = current_time('mysql');

        $settings_data = [
            'setting_key' => self::SETTING_KEY,
            'server_url_encrypted' => Ntfy_Crypto::encrypt($server_url),
            'auth_token_encrypted' => $auth_token_encrypted,
            'include_user_agent' => !empty($input['include_user_agent']) ? 1 : 0,
            'updated_at' => $now,
        ];

        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache is cleared after update.
            $wpdb->update(
                self::settings_table(),
                $settings_data,
                ['setting_key' => self::SETTING_KEY]
            );
        } else {
            $settings_data['created_at'] = $now;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
            $wpdb->insert(self::settings_table(), $settings_data);
        }

        $templates = is_array($input['templates'] ?? null) ? $input['templates'] : [];
        $post = is_array($input['post'] ?? null) ? $input['post'] : [];
        $defaults = self::defaults();

        self::save_post($post);

        self::save_template(
            self::EVENT_POST_PUBLISHED,
            is_array($templates[self::EVENT_POST_PUBLISHED] ?? null) ? $templates[self::EVENT_POST_PUBLISHED] : [],
            $defaults['templates'][self::EVENT_POST_PUBLISHED]
        );

        self::save_template(
            self::EVENT_LOGIN_SUCCESS,
            is_array($templates[self::EVENT_LOGIN_SUCCESS] ?? null) ? $templates[self::EVENT_LOGIN_SUCCESS] : [],
            $defaults['templates'][self::EVENT_LOGIN_SUCCESS]
        );

        self::save_template(
            self::EVENT_LOGIN_FAILURE,
            is_array($templates[self::EVENT_LOGIN_FAILURE] ?? null) ? $templates[self::EVENT_LOGIN_FAILURE] : [],
            $defaults['templates'][self::EVENT_LOGIN_FAILURE]
        );

        self::clear_cache();
    }

    private static function insert_default_rows(): void
    {
        global $wpdb;

        $defaults = self::defaults();
        $now = current_time('mysql');

        if (!self::get_settings_row()) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
            $wpdb->insert(self::settings_table(), [
                'setting_key' => self::SETTING_KEY,
                'server_url_encrypted' => Ntfy_Crypto::encrypt($defaults['server_url']),
                'auth_token_encrypted' => '',
                'include_user_agent' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        self::insert_default_post();

        self::insert_default_template(
            self::EVENT_POST_PUBLISHED,
            $defaults['templates'][self::EVENT_POST_PUBLISHED]
        );

        self::insert_default_template(
            self::EVENT_LOGIN_SUCCESS,
            $defaults['templates'][self::EVENT_LOGIN_SUCCESS]
        );

        self::insert_default_template(
            self::EVENT_LOGIN_FAILURE,
            $defaults['templates'][self::EVENT_LOGIN_FAILURE]
        );

        self::clear_cache();
    }

    private static function insert_default_post(): void
    {
        global $wpdb;

        $existing = self::get_post_rows();

        if (!empty($existing)) {
            return;
        }

        $now = current_time('mysql');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        $wpdb->insert(self::post_table(), [
            'setting_key' => self::SETTING_KEY,
            'event_key' => self::EVENT_POST_PUBLISHED,
            'enabled' => 0,
            'rule_type' => 'all',
            'post_type' => 'post',
            'taxonomy' => 'category',
            'term_id' => 0,
            'topic_encrypted' => '',
            'include_children' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function insert_default_template(string $event_key, array $template): void
    {
        global $wpdb;

        $existing = self::get_template_row($event_key);

        if ($existing) {
            return;
        }

        $now = current_time('mysql');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        $wpdb->insert(self::templates_table(), [
            'setting_key' => self::SETTING_KEY,
            'event_key' => $event_key,
            'enabled' => !empty($template['enabled']) ? 1 : 0,
            'topic_encrypted' => Ntfy_Crypto::encrypt((string) $template['topic']),
            'title_encrypted' => Ntfy_Crypto::encrypt((string) $template['title']),
            'message_encrypted' => Ntfy_Crypto::encrypt((string) $template['message']),
            'priority' => self::sanitize_priority((string) $template['priority']),
            'tags' => sanitize_text_field((string) $template['tags']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function upgrade_failure_template(string $installed_version): void
    {
        global $wpdb;

        if (
            $installed_version === ''
            || version_compare($installed_version, '1.2.0', '>=')
        ) {
            return;
        }

        $row = self::get_template_row(self::EVENT_LOGIN_FAILURE);

        if (!$row) {
            return;
        }

        $old_title = 'Failed WordPress login {username}';
        $old_message = "Failed login attempt for {username} on {site_name}.\nIP {ip}\nTime {time}\nUser Agent {user_agent}";

        if (
            self::decrypt_value($row['title_encrypted'] ?? '') !== $old_title
            || self::decrypt_value($row['message_encrypted'] ?? '') !== $old_message
        ) {
            return;
        }

        $template = self::defaults()['templates'][self::EVENT_LOGIN_FAILURE];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration of an unchanged default template.
        $wpdb->update(
            self::templates_table(),
            [
                'title_encrypted' => Ntfy_Crypto::encrypt((string) $template['title']),
                'message_encrypted' => Ntfy_Crypto::encrypt((string) $template['message']),
                'updated_at' => current_time('mysql'),
            ],
            [
                'setting_key' => self::SETTING_KEY,
                'event_key' => self::EVENT_LOGIN_FAILURE,
            ]
        );

        self::clear_cache();
    }

    private static function save_post(array $post): void
    {
        global $wpdb;

        $now = current_time('mysql');
        $rows = [];
        $seen = [];

        foreach ($post as $row) {
            if (!is_array($row)) {
                continue;
            }

            $event_key = sanitize_key((string) ($row['event_key'] ?? self::EVENT_POST_PUBLISHED));

            if ($event_key !== self::EVENT_POST_PUBLISHED) {
                continue;
            }

            $rule_type = sanitize_key((string) ($row['rule_type'] ?? 'all'));

            if (!in_array($rule_type, ['all', 'taxonomy_term'], true)) {
                $rule_type = 'all';
            }

            $post_type = sanitize_key((string) ($row['post_type'] ?? 'post'));
            $taxonomy = sanitize_key((string) ($row['taxonomy'] ?? 'category'));
            $term_id = max(0, absint($row['term_id'] ?? 0));

            if ($rule_type === 'all') {
                $taxonomy = 'category';
                $term_id = 0;
            }

            $unique_key = implode('|', [
                self::SETTING_KEY,
                $event_key,
                $rule_type,
                $post_type,
                $taxonomy,
                (string) $term_id,
            ]);

            if (isset($seen[$unique_key])) {
                continue;
            }

            $seen[$unique_key] = true;

            $rows[] = [
                'setting_key' => self::SETTING_KEY,
                'event_key' => $event_key,
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'rule_type' => $rule_type,
                'post_type' => $post_type !== '' ? $post_type : 'post',
                'taxonomy' => $taxonomy !== '' ? $taxonomy : 'category',
                'term_id' => $term_id,
                'topic_encrypted' => Ntfy_Crypto::encrypt(
                    self::sanitize_template_line((string) ($row['topic'] ?? ''))
                ),
                'include_children' => !empty($row['include_children']) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($seen[self::SETTING_KEY . '|' . self::EVENT_POST_PUBLISHED . '|all|post|category|0'])) {
            $rows[] = [
                'setting_key' => self::SETTING_KEY,
                'event_key' => self::EVENT_POST_PUBLISHED,
                'enabled' => 0,
                'rule_type' => 'all',
                'post_type' => 'post',
                'taxonomy' => 'category',
                'term_id' => 0,
                'topic_encrypted' => '',
                'include_children' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache is cleared after save.
        $wpdb->delete(self::post_table(), [
            'setting_key' => self::SETTING_KEY,
        ]);

        foreach ($rows as $data) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
            $wpdb->insert(self::post_table(), $data);
        }
    }

    private static function save_template(
        string $event_key,
        array $template,
        array $defaults
    ): void {
        global $wpdb;

        $existing = self::get_template_row($event_key);
        $now = current_time('mysql');

        $data = [
            'setting_key' => self::SETTING_KEY,
            'event_key' => $event_key,
            'enabled' => self::template_enabled($template, $defaults) ? 1 : 0,
            'topic_encrypted' => Ntfy_Crypto::encrypt(
                self::sanitize_template_line(self::template_value($template, 'topic', $defaults))
            ),
            'title_encrypted' => Ntfy_Crypto::encrypt(
                self::sanitize_template_line(self::template_value($template, 'title', $defaults))
            ),
            'message_encrypted' => Ntfy_Crypto::encrypt(
                self::sanitize_template_text(self::template_value($template, 'message', $defaults))
            ),
            'priority' => self::sanitize_priority(
                self::template_value($template, 'priority', $defaults)
            ),
            'tags' => sanitize_text_field(
                wp_unslash(self::template_value($template, 'tags', $defaults))
            ),
            'updated_at' => $now,
        ];

        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; cache is cleared after save.
            $wpdb->update(
                self::templates_table(),
                $data,
                [
                    'setting_key' => self::SETTING_KEY,
                    'event_key' => $event_key,
                ]
            );

            return;
        }

        $data['created_at'] = $now;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        $wpdb->insert(self::templates_table(), $data);
    }

    private static function get_settings_row(): ?array
    {
        global $wpdb;

        $cached = wp_cache_get(self::CACHE_KEY_SETTINGS, self::CACHE_GROUP);

        if (is_array($cached) && array_key_exists('row', $cached)) {
            return is_array($cached['row']) ? $cached['row'] : null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE setting_key = %s',
                self::settings_table(),
                self::SETTING_KEY
            ),
            ARRAY_A
        );

        $row = is_array($row) ? $row : null;

        wp_cache_set(
            self::CACHE_KEY_SETTINGS,
            ['row' => $row],
            self::CACHE_GROUP
        );

        return $row;
    }

    private static function get_post_rows(): array
    {
        global $wpdb;

        $cached = wp_cache_get(self::CACHE_KEY_POST, self::CACHE_GROUP);

        if (is_array($cached)) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE setting_key = %s ORDER BY rule_type ASC, taxonomy ASC, term_id ASC',
                self::post_table(),
                self::SETTING_KEY
            ),
            ARRAY_A
        );

        $rows = is_array($rows) ? $rows : [];

        wp_cache_set(
            self::CACHE_KEY_POST,
            $rows,
            self::CACHE_GROUP
        );

        return $rows;
    }

    private static function format_post(array $rows): array
    {
        $posts = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $posts[] = [
                'id' => (int) ($row['id'] ?? 0),
                'event_key' => (string) ($row['event_key'] ?? 'post_published'),
                'enabled' => !empty($row['enabled']),
                'rule_type' => (string) ($row['rule_type'] ?? 'all'),
                'post_type' => (string) ($row['post_type'] ?? 'post'),
                'taxonomy' => (string) ($row['taxonomy'] ?? 'category'),
                'term_id' => (int) ($row['term_id'] ?? 0),
                'topic' => self::decrypt_value($row['topic_encrypted'] ?? ''),
                'include_children' => !empty($row['include_children']),
            ];
        }

        return $posts;
    }

    private static function get_template_rows(): array
    {
        global $wpdb;

        $cached = wp_cache_get(self::CACHE_KEY_TEMPLATES, self::CACHE_GROUP);

        if (is_array($cached)) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE setting_key = %s',
                self::templates_table(),
                self::SETTING_KEY
            ),
            ARRAY_A
        );

        $templates = [];

        foreach ((array) $rows as $row) {
            if (!empty($row['event_key'])) {
                $templates[$row['event_key']] = $row;
            }
        }

        wp_cache_set(
            self::CACHE_KEY_TEMPLATES,
            $templates,
            self::CACHE_GROUP
        );

        return $templates;
    }

    private static function get_template_row(string $event_key): ?array
    {
        $templates = self::get_template_rows();

        return isset($templates[$event_key]) && is_array($templates[$event_key])
            ? $templates[$event_key]
            : null;
    }

    private static function format_template(array $row, array $defaults): array
    {
        return [
            'enabled' => array_key_exists('enabled', $row)
                ? (bool) (int) $row['enabled']
                : (bool) $defaults['enabled'],
            'topic' => self::decrypt_value(
                $row['topic_encrypted'] ?? '',
                (string) $defaults['topic']
            ),
            'title' => self::decrypt_value(
                $row['title_encrypted'] ?? '',
                (string) $defaults['title']
            ),
            'message' => self::decrypt_value(
                $row['message_encrypted'] ?? '',
                (string) $defaults['message']
            ),
            'priority' => self::sanitize_priority(
                (string) ($row['priority'] ?? $defaults['priority'])
            ),
            'tags' => (string) ($row['tags'] ?? $defaults['tags']),
        ];
    }

    private static function template_enabled(array $template, array $defaults): bool
    {
        if (!array_key_exists('enabled', $template)) {
            return (bool) $defaults['enabled'];
        }

        return !empty($template['enabled']);
    }

    private static function template_value(array $template, string $key, array $defaults): string
    {
        if (!array_key_exists($key, $template)) {
            return (string) ($defaults[$key] ?? '');
        }

        $value = $template[$key];

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return (string) ($defaults[$key] ?? '');
        }

        return (string) $value;
    }

    private static function defaults(): array
    {
        return [
            'server_url' => 'https://ntfy.sh',
            'auth_token' => '',
            'include_user_agent' => false,
            'post' => [
                [
                    'id' => 0,
                    'event_key' => self::EVENT_POST_PUBLISHED,
                    'enabled' => false,
                    'rule_type' => 'all',
                    'post_type' => 'post',
                    'taxonomy' => 'category',
                    'term_id' => 0,
                    'topic' => '',
                    'include_children' => true,
                ],
            ],
            'templates' => [
                self::EVENT_POST_PUBLISHED => [
                    'enabled' => false,
                    'topic' => '',
                    'title' => 'New post published: {post_title}',
                    'message' => "{post_title} was published on {site_name}.\nAuthor {post_author}\nCategory {post_categories}\nURL {post_url}",
                    'priority' => 'default',
                    'tags' => 'newspaper',
                ],
                self::EVENT_LOGIN_SUCCESS => [
                    'enabled' => true,
                    'topic' => 'wordpress-{site_slug}',
                    'title' => 'WordPress login {username}',
                    'message' => "{username} logged into {site_name} from {ip} at {time}.\nRole {roles}\nURL {site_url}",
                    'priority' => 'default',
                    'tags' => 'key',
                ],
                self::EVENT_LOGIN_FAILURE => [
                    'enabled' => false,
                    'topic' => 'wordpress-{site_slug}',
                    'title' => 'Repeated WordPress login failures from {ip}',
                    'message' => "{failure_count} failed logins were recorded from {ip} within {window_minutes} minutes on {site_name}.\nLatest username {username}\nTime {time}\nUser Agent {user_agent}",
                    'priority' => 'high',
                    'tags' => 'warning',
                ],
            ],
        ];
    }

    private static function decrypt_value(?string $value, string $default = ''): string
    {
        if (empty($value)) {
            return $default;
        }

        $decrypted = Ntfy_Crypto::decrypt($value);

        return $decrypted !== '' ? $decrypted : $default;
    }

    private static function sanitize_priority(string $value): string
    {
        $allowed = ['min', 'low', 'default', 'high', 'urgent'];

        return in_array($value, $allowed, true) ? $value : 'default';
    }

    private static function sanitize_server_url(string $value): string
    {
        $url = esc_url_raw(trim(wp_unslash($value)));

        if ($url === '') {
            return 'https://ntfy.sh';
        }

        $scheme = wp_parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'https://ntfy.sh';
        }

        return rtrim($url, '/');
    }

    private static function sanitize_template_line(string $value): string
    {
        return sanitize_text_field(wp_unslash(trim($value)));
    }

    private static function sanitize_template_text(string $value): string
    {
        return sanitize_textarea_field(wp_unslash($value));
    }

    private static function value(array $input, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $input)) {
            return $default;
        }

        $value = $input[$key];

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }
}
