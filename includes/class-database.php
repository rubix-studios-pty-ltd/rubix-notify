<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Database
{
    private const DB_VERSION = '1.1.0';
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

    public static function activate(): void
    {
        global $wpdb;

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

        dbDelta($settings_sql);
        dbDelta($post_sql);
        dbDelta($templates_sql);

        self::insert_default_rows();

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
                    'title' => 'Failed WordPress login {username}',
                    'message' => "Failed login attempt for {username} on {site_name}.\nIP {ip}\nTime {time}\nUser Agent {user_agent}",
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
