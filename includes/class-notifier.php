<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Notifier
{
    private const EVENT_POST_PUBLISHED = 'post_published';
    private const EVENT_LOGIN_SUCCESS = 'login_success';
    private const EVENT_LOGIN_FAILURE = 'login_failure';

    public static function init(): void
    {
        add_action('wp_login', [self::class, 'send_success'], 10, 2);

        add_action('transition_post_status', [self::class, 'send_post_published'], 10, 3);
    }

    public static function send_success(string $user_login, WP_User $user): void
    {
        $settings = Ntfy_Database::get_settings();
        $template = self::template($settings, self::EVENT_LOGIN_SUCCESS);

        if (empty($template['enabled'])) {
            return;
        }

        $variables = self::variables(
            self::EVENT_LOGIN_SUCCESS,
            'success',
            $user_login,
            $user,
            $settings
        );

        self::publish($settings, $template, $variables, false);
    }

    public static function send_failure(
        string $username,
        string $ip_address = '',
        int $failure_count = 1
    ): array {
        $settings = Ntfy_Database::get_settings();
        $template = self::template($settings, self::EVENT_LOGIN_FAILURE);

        if (empty($template['enabled'])) {
            return [
                'success' => false,
                'message' => 'Failed-login alerts are disabled.',
            ];
        }

        $variables = self::variables(
            self::EVENT_LOGIN_FAILURE,
            'failure',
            $username,
            null,
            $settings,
            $ip_address,
            [
                'failure_count' => (string) $failure_count,
                'window_minutes' => (string) (Ntfy_Login_Security::WINDOW_SECONDS / MINUTE_IN_SECONDS),
            ]
        );

        return self::publish($settings, $template, $variables, false);
    }

    public static function failure_alert_enabled(): bool
    {
        $settings = Ntfy_Database::get_settings();
        $template = self::template($settings, self::EVENT_LOGIN_FAILURE);

        return !empty($template['enabled']);
    }

    public static function send_test(): array
    {
        $settings = Ntfy_Database::get_settings();
        $template = self::template($settings, self::EVENT_LOGIN_SUCCESS);
        $user = wp_get_current_user();

        $variables = self::variables(
            'test_notification',
            'test',
            $user instanceof WP_User && $user->exists() ? $user->user_login : 'test',
            $user instanceof WP_User && $user->exists() ? $user : null,
            $settings
        );

        return self::publish($settings, $template, $variables, true);
    }

    public static function send_post_published(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $settings = Ntfy_Database::get_settings();
        $template = self::template($settings, self::EVENT_POST_PUBLISHED);
        $post_setting = self::post_setting($settings, $post);

        if (!$post_setting || empty($post_setting['enabled'])) {
            return;
        }

        $template['topic'] = (string) ($post_setting['topic'] ?? '');

        $variables = self::post_variables(
            self::EVENT_POST_PUBLISHED,
            'published',
            $post,
            $settings
        );

        self::publish($settings, $template, $variables, false);
    }

    private static function template(array $settings, string $event_key): array
    {
        $templates = is_array($settings['templates'] ?? null) ? $settings['templates'] : [];
        $template = is_array($templates[$event_key] ?? null) ? $templates[$event_key] : [];

        return [
            'enabled' => (bool) ($template['enabled'] ?? false),
            'topic' => (string) ($template['topic'] ?? ''),
            'title' => (string) ($template['title'] ?? ''),
            'message' => (string) ($template['message'] ?? ''),
            'priority' => (string) ($template['priority'] ?? 'default'),
            'tags' => (string) ($template['tags'] ?? ''),
        ];
    }

    private static function publish(
        array $settings,
        array $template,
        array $variables,
        bool $blocking
    ): array {
        $server_url = rtrim((string) ($settings['server_url'] ?? ''), '/');
        $site_url = home_url('/');

        if ($server_url === '') {
            return [
                'success' => false,
                'message' => 'Server URL is empty.',
            ];
        }

        $topic = self::clean_topic(
            Ntfy_Template::render((string) $template['topic'], $variables)
        );

        $title = self::clean_header(
            Ntfy_Template::render((string) $template['title'], $variables),
            120
        );

        $message = Ntfy_Template::render((string) $template['message'], $variables);

        if ($topic === '') {
            return [
                'success' => false,
                'message' => 'Topic is empty.',
            ];
        }

        $headers = [
            'Title' => $title,
            'Priority' => self::clean_header((string) $template['priority'], 20),
            'Click' => self::clean_header((string) ($variables['post_url'] ?? $site_url), 300),
        ];

        if (!empty($template['tags'])) {
            $headers['Tags'] = self::clean_header((string) $template['tags'], 120);
        }

        if (!empty($settings['auth_token'])) {
            $headers['Authorization'] = 'Bearer ' . (string) $settings['auth_token'];
        }

        $response = wp_remote_post($server_url . '/' . rawurlencode($topic), [
            'timeout' => 5,
            'redirection' => 0,
            'blocking' => $blocking,
            'headers' => $headers,
            'body' => $message,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if (!$blocking) {
            return [
                'success' => true,
                'message' => 'Queued.',
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'message' => 'Test notification sent.',
            ];
        }

        return [
            'success' => false,
            'message' => 'ntfy returned HTTP ' . $code . '.',
        ];
    }

    private static function variables(
        string $event,
        string $status,
        string $username,
        ?WP_User $user,
        array $settings,
        string $ip_address = '',
        array $extra = []
    ): array {
        $site_url = home_url('/');
        $host = wp_parse_url($site_url, PHP_URL_HOST);

        $variables = [
            'site_name' => get_bloginfo('name'),
            'site_url' => $site_url,
            'site_slug' => sanitize_title($host ?: get_bloginfo('name')),
            'event' => $event,
            'status' => $status,
            'username' => $username,
            'display_name' => $user ? $user->display_name : '',
            'user_email' => $user ? $user->user_email : '',
            'roles' => $user ? implode(', ', $user->roles) : '',
            'ip' => $ip_address !== ''
                ? $ip_address
                : Ntfy_Login_Security::client_ip(),
            'user_agent' => !empty($settings['include_user_agent'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''))
                : '',
            'time' => current_time('mysql'),
            'failure_count' => '',
            'window_minutes' => '',
        ];

        return array_merge($variables, $extra);
    }

    private static function clean_header(string $value, int $max_length = 200): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        $value = trim($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $max_length)
            : substr($value, 0, $max_length);
    }

    private static function clean_topic(string $value): string
    {
        $value = str_replace(["\r", "\n", '/', '\\'], '-', $value);
        $value = trim($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 120)
            : substr($value, 0, 120);
    }

    private static function post_setting(array $settings, WP_Post $post): ?array
    {
        $rules = is_array($settings['post'] ?? null) ? $settings['post'] : [];

        $fallback = null;

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (($rule['event_key'] ?? '') !== self::EVENT_POST_PUBLISHED) {
                continue;
            }

            if (($rule['post_type'] ?? 'post') !== $post->post_type) {
                continue;
            }

            if (($rule['rule_type'] ?? '') === 'all') {
                $fallback = $rule;
                continue;
            }

            if (($rule['rule_type'] ?? '') !== 'taxonomy_term') {
                continue;
            }

            $taxonomy = (string) ($rule['taxonomy'] ?? 'category');
            $term_id = (int) ($rule['term_id'] ?? 0);

            if ($term_id <= 0 || !taxonomy_exists($taxonomy)) {
                continue;
            }

            if (self::post_matches_term($post, $taxonomy, $term_id, !empty($rule['include_children']))) {
                return $rule;
            }
        }

        return $fallback;
    }

    private static function post_matches_term(
        WP_Post $post,
        string $taxonomy,
        int $rule_id,
        bool $include_children
    ): bool {
        $terms = wp_get_post_terms($post->ID, $taxonomy, [
            'fields' => 'ids',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }

        $term_ids = array_map('intval', $terms);

        if (in_array($rule_id, $term_ids, true)) {
            return true;
        }

        if (!$include_children) {
            return false;
        }

        foreach ($term_ids as $term_id) {
            $ancestors = get_ancestors($term_id, $taxonomy, 'taxonomy');
            $ancestors = array_map('intval', $ancestors);

            if (in_array($rule_id, $ancestors, true)) {
                return true;
            }
        }

        return false;
    }

    private static function post_variables(
        string $event,
        string $status,
        WP_Post $post,
        array $settings
    ): array {
        $author = get_user_by('id', (int) $post->post_author);
        $categories = get_the_category($post->ID);

        $category_names = [];

        if (is_array($categories)) {
            foreach ($categories as $category) {
                if ($category instanceof WP_Term) {
                    $category_names[] = $category->name;
                }
            }
        }

        return array_merge(
            self::variables(
                $event,
                $status,
                $author instanceof WP_User ? $author->user_login : '',
                $author instanceof WP_User ? $author : null,
                $settings
            ),
            [
                'post_id' => (string) $post->ID,
                'post_title' => get_the_title($post),
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'post_url' => get_permalink($post),
                'post_author' => $author instanceof WP_User ? $author->display_name : '',
                'post_date' => get_post_time('Y-m-d H:i:s', false, $post),
                'post_modified' => get_post_modified_time('Y-m-d H:i:s', false, $post),
                'post_categories' => implode(', ', $category_names),
            ]
        );
    }
}
