<?php

namespace App\Support;

class NotificationUrl
{
    public static function resolve(?string $url): string
    {
        $parts = $url ? parse_url($url) : false;
        $path = $parts['path'] ?? '';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return route('notifications.index', [], false);
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
