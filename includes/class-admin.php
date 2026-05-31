<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Admin
{
    private static $hook = '';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function menu(): void
    {
        self::$hook = add_menu_page(
            'Notification',
            'Notification',
            'manage_options',
            'rubix-notify',
            [self::class, 'render'],
            'dashicons-bell',
            59
        );
    }

    public static function enqueue(string $hook): void
    {
        if ($hook !== self::$hook) {
            return;
        }

        $asset_file = NTFY_PATH . 'admin/index.asset.php';
        $script_file = NTFY_PATH . 'admin/index.js';

        if (!file_exists($asset_file) || !file_exists($script_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'rubix-notify',
            NTFY_URL . 'admin/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_add_inline_script(
            'rubix-notify',
            'window.NTFY_ALERTS = ' . wp_json_encode([
                'root' => esc_url_raw(rest_url()),
                'namespace' => 'rubix-notify/v1',
                'nonce' => wp_create_nonce('wp_rest'),
            ]) . ';',
            'before'
        );

        wp_enqueue_style('wp-components');
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'rubix-notify'
                )
            );
        }

        $asset_file = NTFY_PATH . 'admin/index.asset.php';
        $script_file = NTFY_PATH . 'admin/index.js';

        echo '<div class="wrap">';
        echo '<h1>Notification</h1>';

        if (!file_exists($asset_file) || !file_exists($script_file)) {
            echo '<div class="notice notice-warning"><p>';
            echo 'Admin app is not built. Run <code>pnpm install</code> then <code>pnpm build</code>.';
            echo '</p></div>';
        }

        echo '<div id="rubix-notify"></div>';
        echo '</div>';
    }
}
