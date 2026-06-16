<?php
declare(strict_types=1);

/**
 * CORS + JSON helpers. Including this file sends CORS headers and short-circuits
 * OPTIONS preflight requests. In production the API is same-origin with the SPA,
 * so cors_origin normally matches the app and these headers are a no-op safety net.
 */

$__origin = cfg('cors_origin', '*');
header('Access-Control-Allow-Origin: ' . $__origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** Emit a JSON response and stop. */
function jout($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Emit a JSON error and stop. */
function jfail(string $message, int $status = 400, array $extra = []): void
{
    jout(array_merge(['error' => $message], $extra), $status);
}

/** Decode the JSON request body into an array. */
function jin(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jfail('Invalid JSON body.', 400);
    }
    return $data;
}

/** The HTTP method. */
function method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}
