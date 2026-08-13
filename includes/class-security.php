<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Login_Security
{
    public const ALERT_THRESHOLD = 10;
    public const WINDOW_SECONDS = HOUR_IN_SECONDS;
    public const RETENTION_DAYS = 90;

    private const CLEANUP_HOOK = 'rubix_notify_cleanup_login_attempts';

    private const CLOUDFLARE_CIDRS = [
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '108.162.192.0/18',
        '131.0.72.0/22',
        '141.101.64.0/18',
        '162.158.0.0/15',
        '172.64.0.0/13',
        '173.245.48.0/20',
        '188.114.96.0/20',
        '190.93.240.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public static function activate(): void
    {
        self::schedule_cleanup();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }

    public static function init(): void
    {
        add_filter('authenticate', [self::class, 'block_banned_ip'], 100, 3);
        add_filter('wp_authenticate_user', [self::class, 'block_banned_user'], 5, 2);
        add_action('wp_login_failed', [self::class, 'record_failure'], 10, 2);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);

        self::schedule_cleanup();
    }

    /**
     * @param null|WP_User|WP_Error $user Authentication result from WordPress.
     * @return null|WP_User|WP_Error
     */
    public static function block_banned_ip($user, string $username, string $password)
    {
        unset($username, $password);

        $ip_address = self::client_ip();

        if ($ip_address === '') {
            return $user;
        }

        if (Ntfy_Database::get_login_ip_status($ip_address) !== 'banned') {
            return $user;
        }

        return new WP_Error(
            'rubix_notify_ip_banned',
            __('Login access from this IP address has been blocked.', 'rubix-notify')
        );
    }

    /**
     * Stops the password hash check early for a known user on a banned IP.
     * The later authenticate filter remains the final enforcement point.
     *
     * @param WP_User|WP_Error $user WordPress authentication result.
     * @return WP_User|WP_Error
     */
    public static function block_banned_user($user, string $password)
    {
        return self::block_banned_ip($user, '', $password);
    }

    public static function record_failure(string $username, WP_Error $error): void
    {
        $ip_address = self::client_ip();
        $error_code = (string) $error->get_error_code();

        $result = Ntfy_Database::record_login_failure(
            $ip_address,
            $username,
            $error_code
        );

        $status = (string) ($result['status'] ?? '');

        if ($ip_address === '' || $status === 'whitelisted' || $status === 'banned') {
            return;
        }

        $failure_count = (int) ($result['failures_last_hour'] ?? 0);

        if ($failure_count < self::ALERT_THRESHOLD) {
            return;
        }

        if (!Ntfy_Notifier::failure_alert_enabled()) {
            return;
        }

        $claimed_at = Ntfy_Database::claim_login_alert(
            $ip_address,
            self::WINDOW_SECONDS
        );

        if ($claimed_at === null) {
            return;
        }

        $notification = Ntfy_Notifier::send_failure(
            $username,
            $ip_address,
            $failure_count
        );

        if (empty($notification['success'])) {
            Ntfy_Database::release_login_alert($ip_address, $claimed_at);
        }
    }

    public static function cleanup(): void
    {
        Ntfy_Database::delete_old_login_attempts(self::RETENTION_DAYS);
    }

    public static function client_ip(): string
    {
        $remote_address = '';

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $remote_address = sanitize_text_field(
                wp_unslash((string) $_SERVER['REMOTE_ADDR'])
            );
        }

        $remote_address = self::normalize_ip($remote_address);
        $client_address = $remote_address;

        if (
            $remote_address !== ''
            && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])
            && self::is_cloudflare_address($remote_address)
        ) {
            $cloudflare_address = self::normalize_ip(
                sanitize_text_field(
                    wp_unslash((string) $_SERVER['HTTP_CF_CONNECTING_IP'])
                )
            );

            if ($cloudflare_address !== '') {
                $client_address = $cloudflare_address;
            }
        }

        /**
         * Filters the client IP used for login auditing and access rules.
         *
         * REMOTE_ADDR is used by default. CF-Connecting-IP is accepted only when
         * the direct connection is from a published Cloudflare network range.
         * Other trusted reverse proxies can supply their verified visitor IP.
         *
         * @param string $client_address Validated client IP address.
         * @param array  $server         Current server request values.
         * @param string $remote_address Validated direct peer IP address.
         */
        $filtered = apply_filters(
            'rubix_notify_client_ip',
            $client_address,
            $_SERVER,
            $remote_address
        );

        return self::normalize_ip(is_string($filtered) ? $filtered : '');
    }

    public static function normalize_ip(string $ip_address): string
    {
        $ip_address = trim($ip_address);

        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            return '';
        }

        $packed = @inet_pton($ip_address);

        if ($packed === false) {
            return '';
        }

        $normalized = @inet_ntop($packed);

        return is_string($normalized) ? strtolower($normalized) : '';
    }

    private static function is_cloudflare_address(string $ip_address): bool
    {
        /**
         * Filters the Cloudflare network ranges trusted for CF-Connecting-IP.
         *
         * @param string[] $ranges Cloudflare IPv4 and IPv6 CIDR ranges.
         */
        $ranges = apply_filters(
            'rubix_notify_cloudflare_cidrs',
            self::CLOUDFLARE_CIDRS
        );

        if (!is_array($ranges)) {
            return false;
        }

        foreach ($ranges as $range) {
            if (is_string($range) && self::ip_in_cidr($ip_address, $range)) {
                return true;
            }
        }

        return false;
    }

    private static function ip_in_cidr(string $ip_address, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $ip_binary = @inet_pton($ip_address);
        $network_binary = @inet_pton($parts[0]);

        if (
            $ip_binary === false
            || $network_binary === false
            || strlen($ip_binary) !== strlen($network_binary)
        ) {
            return false;
        }

        $prefix = filter_var(
            $parts[1],
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0,
                    'max_range' => strlen($ip_binary) * 8,
                ],
            ]
        );

        if ($prefix === false) {
            return false;
        }

        $whole_bytes = intdiv((int) $prefix, 8);
        $remaining_bits = (int) $prefix % 8;

        if (
            $whole_bytes > 0
            && substr($ip_binary, 0, $whole_bytes) !== substr($network_binary, 0, $whole_bytes)
        ) {
            return false;
        }

        if ($remaining_bits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remaining_bits)) & 0xff;

        return (ord($ip_binary[$whole_bytes]) & $mask)
            === (ord($network_binary[$whole_bytes]) & $mask);
    }

    private static function schedule_cleanup(): void
    {
        if (wp_next_scheduled(self::CLEANUP_HOOK)) {
            return;
        }

        wp_schedule_event(
            time() + HOUR_IN_SECONDS,
            'daily',
            self::CLEANUP_HOOK
        );
    }
}
