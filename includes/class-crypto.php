<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Crypto
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $key = self::key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plain, $nonce, $key);

            return 'sodium:v1:' . base64_encode($nonce . $cipher);
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('No supported encryption extension is available.');
        }

        $iv = random_bytes(12);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return 'openssl-gcm:v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $encrypted): string
    {
        if (!$encrypted) {
            return '';
        }

        $key = self::key();

        if (strpos($encrypted, 'sodium:v1:') === 0) {
            if (!function_exists('sodium_crypto_secretbox_open')) {
                return '';
            }

            $payload = base64_decode(substr($encrypted, strlen('sodium:v1:')), true);

            if ($payload === false) {
                return '';
            }

            $nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $nonce = substr($payload, 0, $nonce_size);
            $cipher = substr($payload, $nonce_size);

            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

            return $plain === false ? '' : $plain;
        }

        if (strpos($encrypted, 'openssl-gcm:v1:') === 0) {
            if (!function_exists('openssl_decrypt')) {
                return '';
            }

            $payload = base64_decode(substr($encrypted, strlen('openssl-gcm:v1:')), true);

            if ($payload === false || strlen($payload) < 28) {
                return '';
            }

            $iv = substr($payload, 0, 12);
            $tag = substr($payload, 12, 16);
            $cipher = substr($payload, 28);

            $plain = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $plain === false ? '' : $plain;
        }

        return '';
    }

    private static function key(): string
    {
        $material = '';

        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
            if (defined($constant)) {
                $material .= (string) constant($constant);
            }
        }

        if ($material === '' && function_exists('wp_salt')) {
            $material = wp_salt('auth') . wp_salt('secure_auth') . wp_salt('logged_in') . wp_salt('nonce');
        }

        return hash('sha256', $material, true);
    }
}
