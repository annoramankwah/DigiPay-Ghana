<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Appends each static asset's mtime as a cache-busting query string, so a
 * deploy that changes app.css/app.js is picked up immediately instead of
 * being served from a browser's (or CDN's) stale cache indefinitely.
 */
class Assets
{
    public static function version(string $publicRelativePath): string
    {
        $file = dirname(__DIR__, 2) . '/public/' . ltrim($publicRelativePath, '/');
        $mtime = @filemtime($file);
        return $mtime !== false ? (string) $mtime : '1';
    }
}
