<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Ntfy_Template
{
    public static function render(string $template, array $variables): string
    {
        $replace = [];

        foreach ($variables as $key => $value) {
            $replace["{{$key}}"] = (string) $value;
        }

        return strtr($template, $replace);
    }

    public static function variables(): array
    {
        return [
            '{site_name}',
            '{site_url}',
            '{site_slug}',
            '{event}',
            '{status}',
            '{username}',
            '{display_name}',
            '{user_email}',
            '{roles}',
            '{ip}',
            '{user_agent}',
            '{time}',

            '{post_id}',
            '{post_title}',
            '{post_type}',
            '{post_status}',
            '{post_url}',
            '{post_author}',
            '{post_date}',
            '{post_modified}',
            '{post_categories}',
        ];
    }
}
