<?php
declare(strict_types=1);

/**
 * Stateless JWT (HS256) auth. No sessions table — the token is the session.
 * Helpers: jwt_encode / jwt_decode, current_user (optional), require_user (mandatory).
 */

function base64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function base64url_decode(string $txt): string
{
    $pad = strlen($txt) % 4;
    if ($pad) {
        $txt .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($txt, '-_', '+/')) ?: '';
}

function jwt_encode(array $payload, int $ttlSeconds = 1209600): string
{
    $secret = (string) cfg('jwt_secret', '');
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload = array_merge(['iat' => $now, 'exp' => $now + $ttlSeconds], $payload);

    $h = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $p = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', "{$h}.{$p}", $secret, true);
    return "{$h}.{$p}." . base64url_encode($sig);
}

/** Returns the decoded payload, or null if invalid/expired. */
function jwt_decode(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $secret = (string) cfg('jwt_secret', '');
    $expected = base64url_encode(hash_hmac('sha256', "{$h}.{$p}", $secret, true));
    if (!hash_equals($expected, $s)) {
        return null;
    }
    $payload = json_decode(base64url_decode($p), true);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
        return null;
    }
    return $payload;
}

/** Extract the Bearer token from the Authorization header. */
function bearer_token(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if (!$hdr && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $hdr = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.+)/i', (string) $hdr, $m)) {
        return trim($m[1]);
    }
    return null;
}

/** Returns the authenticated user row, or null when no/invalid token. */
function current_user(): ?array
{
    $token = bearer_token();
    if (!$token) {
        return null;
    }
    $payload = jwt_decode($token);
    if (!$payload || empty($payload['sub'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, plan, plan_expires_at FROM users WHERE id = ?');
    $stmt->execute([(string) $payload['sub']]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    // Lapsed Pro reverts to free at read time (webhook also handles this).
    if ($user['plan'] === 'pro' && !empty($user['plan_expires_at'])
        && strtotime($user['plan_expires_at']) < time()) {
        $user['plan'] = 'free';
    }
    return $user;
}

/** Returns the authenticated user or sends 401 and stops. */
function require_user(): array
{
    $user = current_user();
    if (!$user) {
        jfail('Authentication required.', 401);
    }
    return $user;
}

/** Whether a user is an admin. Defensive: missing column (pre-migration) => false. */
function user_is_admin(string $userId): bool
{
    try {
        $stmt = db()->prepare('SELECT is_admin FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (bool) (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return false;
    }
}

/** Returns the authenticated admin user, or sends 403 and stops. */
function require_admin(): array
{
    $user = require_user();
    if (!user_is_admin((string) $user['id'])) {
        jfail('Admin access required.', 403);
    }
    return $user;
}
