<?php
declare(strict_types=1);

/**
 * Common bootstrap for every API endpoint. Loads config + helpers and sends
 * CORS headers (handling OPTIONS preflight). Include this first in each endpoint.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/cors.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/limits.php';

/** Ownership helper: load a session owned by the user, or 404. */
function require_owned_session(string $sessionId, string $userId): array
{
    $stmt = db()->prepare('SELECT * FROM sessions WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$sessionId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Session not found.', 404);
    }
    return $row;
}

/** Ownership helper: load a receipt the user owns (via its session), or 404. */
function require_owned_receipt(string $receiptId, string $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.* FROM receipts r
         JOIN sessions s ON s.id = r.session_id
         WHERE r.id = ? AND s.owner_user_id = ?'
    );
    $stmt->execute([$receiptId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Receipt not found.', 404);
    }
    return $row;
}
