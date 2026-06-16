<?php
declare(strict_types=1);

/**
 * Common bootstrap for every API endpoint. Loads config + helpers and sends
 * CORS headers (handling OPTIONS preflight). Include this first in each endpoint.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/lib/errors.php';   // log errors + clean JSON 500s (after config, before the rest)
require __DIR__ . '/db.php';
require __DIR__ . '/cors.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/lib/limits.php';

// ── Ownership helpers (all four live here so access control is in one place) ──

/** Load a session owned by the user, or 404. */
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

/** Load a receipt the user owns (via its session), or 404. */
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

/** Load a member the user owns (via its session), or 404. */
function require_owned_member(string $memberId, string $userId): array
{
    $stmt = db()->prepare(
        'SELECT m.* FROM members m
         JOIN sessions s ON s.id = m.session_id
         WHERE m.id = ? AND s.owner_user_id = ?'
    );
    $stmt->execute([$memberId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Member not found.', 404);
    }
    return $row;
}

/** Load a line item the user owns (via receipt→session), or 404. */
function require_owned_line_item(string $lineItemId, string $userId): array
{
    $stmt = db()->prepare(
        'SELECT li.* FROM line_items li
         JOIN receipts r ON r.id = li.receipt_id
         JOIN sessions s ON s.id = r.session_id
         WHERE li.id = ? AND s.owner_user_id = ?'
    );
    $stmt->execute([$lineItemId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Line item not found.', 404);
    }
    return $row;
}

/** Load a contact owned by the user, or 404 (includes soft-deleted). */
function require_owned_contact(string $contactId, string $userId): array
{
    $stmt = db()->prepare('SELECT * FROM contacts WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$contactId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Contact not found.', 404);
    }
    return $row;
}

/** Find an active contact by exact name for this user, or create one. Returns its id. */
function find_or_create_contact(string $userId, string $displayName): string
{
    $name = trim($displayName);
    $stmt = db()->prepare(
        'SELECT id FROM contacts WHERE owner_user_id = ? AND deleted_at IS NULL AND display_name = ? LIMIT 1'
    );
    $stmt->execute([$userId, $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (string) $id;
    }
    $id = uuidv4();
    $ins = db()->prepare('INSERT INTO contacts (id, owner_user_id, display_name) VALUES (?, ?, ?)');
    $ins->execute([$id, $userId, $name]);
    return $id;
}

// ── Shared validation / write helpers ──

/** Normalize a user/AI-supplied currency to a 3-letter ISO code, else the fallback. */
function normalize_currency(?string $raw, ?string $fallback = null): ?string
{
    $cur = strtoupper(trim((string) $raw));
    return preg_match('/^[A-Z]{3}$/', $cur) ? $cur : $fallback;
}

/** Set of valid member ids for a session, as a lookup (id => true). */
function session_member_id_set(string $sessionId): array
{
    $stmt = db()->prepare('SELECT id FROM members WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    return array_flip(array_column($stmt->fetchAll(), 'id'));
}

/**
 * Replace the full share set of one line item (delete-then-insert). Skips members
 * not in $validSet, de-dupes, clamps non-positive weights to 1. Caller manages
 * the surrounding transaction.
 */
function replace_item_shares(PDO $pdo, string $lineItemId, array $shares, array $validSet): void
{
    $del = $pdo->prepare('DELETE FROM item_shares WHERE line_item_id = ?');
    $del->execute([$lineItemId]);

    $ins = $pdo->prepare('INSERT INTO item_shares (id, line_item_id, member_id, weight) VALUES (?, ?, ?, ?)');
    $seen = [];
    foreach ($shares as $share) {
        $memberId = (string) ($share['member_id'] ?? '');
        if (!isset($validSet[$memberId]) || isset($seen[$memberId])) {
            continue;
        }
        $seen[$memberId] = true;
        $weight = (float) ($share['weight'] ?? 1);
        if ($weight <= 0) {
            $weight = 1;
        }
        $ins->execute([uuidv4(), $lineItemId, $memberId, round($weight, 3)]);
    }
}

// ── Private uploads helpers ──

/** Whether a name matches our generated upload filename (defends image_path writes). */
function valid_upload_name(?string $name): bool
{
    return is_string($name) && (bool) preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $name);
}

/** Absolute path of a stored upload (basename-guarded). */
function uploads_path(string $filename): string
{
    $dir = rtrim((string) (cfg('uploads_dir') ?: (__DIR__ . '/../private_uploads')), '/');
    return $dir . '/' . basename($filename);
}

/** Best-effort delete of a stored upload (no error if missing). */
function delete_upload(?string $filename): void
{
    if (!valid_upload_name($filename)) {
        return;
    }
    $p = uploads_path((string) $filename);
    if (is_file($p)) {
        @unlink($p);
    }
}
