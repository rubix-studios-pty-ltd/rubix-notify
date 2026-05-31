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
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/test', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'send_test'],
            'permission_callback' => [self::class, 'can_manage'],
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
}
