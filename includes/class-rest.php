<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Rest
{
    private const NAMESPACE = 'rubix-notify/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_settings'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save_settings'],
                'permission_callback' => [self::class, 'can_manage'],
                'args' => [
                    'server_url' => [
                        'type' => 'string',
                        'required' => true,
                    ],
                    'include_user_agent' => [
                        'type' => 'boolean',
                        'required' => false,
                    ],
                    'auth_token' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                    'clear_auth_token' => [
                        'type' => 'boolean',
                        'required' => false,
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/test', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'send_test'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE, '/security', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_security'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE, '/security/ip', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'update_ip_rule'],
            'permission_callback' => [self::class, 'can_manage'],
            'args' => [
                'ip' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => static function ($value): bool {
                        return is_string($value)
                            && Ntfy_Login_Security::normalize_ip($value) !== '';
                    },
                ],
                'action' => [
                    'type' => 'string',
                    'required' => true,
                    'enum' => ['ban', 'unban', 'whitelist', 'remove_whitelist'],
                ],
            ],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function get_settings(): WP_REST_Response
    {
        return new WP_REST_Response(
            Ntfy_Database::get_public_settings()
        );
    }

    public static function save_settings(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (!is_array($params)) {
            $params = [];
        }

        Ntfy_Database::save_settings($params);

        return new WP_REST_Response(
            Ntfy_Database::get_public_settings()
        );
    }

    public static function send_test(): WP_REST_Response
    {
        $result = Ntfy_Notifier::send_test();

        return new WP_REST_Response(
            $result,
            !empty($result['success']) ? 200 : 400
        );
    }

    public static function get_security(): WP_REST_Response
    {
        return new WP_REST_Response(self::security_payload());
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public static function update_ip_rule(WP_REST_Request $request)
    {
        $ip_address = Ntfy_Login_Security::normalize_ip(
            (string) $request->get_param('ip')
        );
        $action = sanitize_key((string) $request->get_param('action'));

        if ($ip_address === '') {
            return new WP_Error(
                'rubix_notify_invalid_ip',
                __('Enter a valid IPv4 or IPv6 address.', 'rubix-notify'),
                ['status' => 400]
            );
        }

        $current_ip = Ntfy_Login_Security::client_ip();

        if ($action === 'ban' && $current_ip !== '' && $ip_address === $current_ip) {
            return new WP_Error(
                'rubix_notify_current_ip',
                __('The IP address used by your current session cannot be banned.', 'rubix-notify'),
                ['status' => 400]
            );
        }

        $statuses = [
            'ban' => 'banned',
            'unban' => 'observed',
            'whitelist' => 'whitelisted',
            'remove_whitelist' => 'observed',
        ];

        if (!isset($statuses[$action])) {
            return new WP_Error(
                'rubix_notify_invalid_action',
                __('The requested IP action is not supported.', 'rubix-notify'),
                ['status' => 400]
            );
        }

        $updated = Ntfy_Database::set_login_ip_status(
            $ip_address,
            $statuses[$action],
            get_current_user_id()
        );

        if (!$updated) {
            return new WP_Error(
                'rubix_notify_ip_update_failed',
                __('The IP rule could not be updated.', 'rubix-notify'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response(self::security_payload());
    }

    private static function security_payload(): array
    {
        $payload = Ntfy_Database::get_login_security_snapshot();

        $payload['current_ip'] = Ntfy_Login_Security::client_ip();
        $payload['alert_threshold'] = Ntfy_Login_Security::ALERT_THRESHOLD;
        $payload['window_minutes'] = (int) (
            Ntfy_Login_Security::WINDOW_SECONDS / MINUTE_IN_SECONDS
        );
        $payload['retention_days'] = Ntfy_Login_Security::RETENTION_DAYS;
        $payload['alerts_enabled'] = Ntfy_Notifier::failure_alert_enabled();

        return $payload;
    }
}
