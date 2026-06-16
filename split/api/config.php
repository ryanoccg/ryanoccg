<?php
declare(strict_types=1);

/**
 * Loads configuration from a secrets file kept OUTSIDE the web root.
 *
 * Search order (first match wins):
 *   1. env APP_SECRETS (explicit path)
 *   2. ~/app_private/secrets.php   (production cPanel layout)
 *   3. ../../app_private/secrets.php relative to this file
 *   4. ./secrets.php               (local dev only — gitignored)
 *
 * secrets.php must `return` an associative array — see secrets.example.php.
 */
function app_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $candidates = [];
    if (getenv('APP_SECRETS')) {
        $candidates[] = getenv('APP_SECRETS');
    }
    if (!empty($_SERVER['HOME'])) {
        $candidates[] = rtrim($_SERVER['HOME'], '/') . '/app_private/secrets.php';
    }
    $candidates[] = __DIR__ . '/../../app_private/secrets.php';
    $candidates[] = __DIR__ . '/secrets.php';

    foreach ($candidates as $path) {
        if ($path && is_file($path)) {
            $loaded = require $path;
            $cfg = is_array($loaded) ? $loaded : [];
            return $cfg;
        }
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server not configured: secrets file missing.']);
    exit;
}

/** Fetch a single config value with a default. Supports 'db.host' dot paths. */
function cfg(string $key, $default = null)
{
    $cfg = app_config();
    if (array_key_exists($key, $cfg)) {
        return $cfg[$key];
    }
    if (str_contains($key, '.')) {
        $node = $cfg;
        foreach (explode('.', $key) as $part) {
            if (is_array($node) && array_key_exists($part, $node)) {
                $node = $node[$part];
            } else {
                return $default;
            }
        }
        return $node;
    }
    return $default;
}
