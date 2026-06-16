<?php
declare(strict_types=1);

/**
 * Server-side, authoritative plan limits + usage tracking.
 * 'super' bypasses every check. Never trust the client for limits.
 *
 * Over-limit responses use HTTP 402 (Payment Required) with an upgrade hint
 * the SPA turns into a friendly prompt.
 */

const PLAN_LIMITS = [
    'free'  => ['sessions' => 2,   'ocr_per_month' => 10,  'members_per_session' => 5],
    'pro'   => ['sessions' => -1,  'ocr_per_month' => 200, 'members_per_session' => -1],
    'super' => ['sessions' => -1,  'ocr_per_month' => -1,  'members_per_session' => -1],
];

/** -1 means unlimited. */
function plan_limits(string $plan): array
{
    return PLAN_LIMITS[$plan] ?? PLAN_LIMITS['free'];
}

/** Whether ads should render for this plan. */
function ads_enabled(string $plan): bool
{
    return $plan === 'free';
}

function current_period(): string
{
    return gmdate('Y-m');
}

/** OCR scans used this month for a user. */
function ocr_used_this_month(string $userId): int
{
    $stmt = db()->prepare(
        'SELECT ocr_scans FROM usage_counters WHERE user_id = ? AND period = ?'
    );
    $stmt->execute([$userId, current_period()]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/** Atomically increment this month's OCR counter. */
function increment_ocr(string $userId): void
{
    $stmt = db()->prepare(
        'INSERT INTO usage_counters (user_id, period, ocr_scans)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE ocr_scans = ocr_scans + 1'
    );
    $stmt->execute([$userId, current_period()]);
}

function over_limit(string $action): void
{
    jfail(
        "You've reached your plan limit for this action. Upgrade to Pro to continue.",
        402,
        ['code' => 'limit_reached', 'action' => $action]
    );
}

/** Guard: creating a session. */
function guard_create_session(array $user): void
{
    $limit = plan_limits($user['plan'])['sessions'];
    if ($limit < 0) {
        return;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM sessions WHERE owner_user_id = ?');
    $stmt->execute([(string) $user['id']]);
    if ((int) $stmt->fetchColumn() >= $limit) {
        over_limit('create_session');
    }
}

/** Guard: adding a member to a session. */
function guard_add_member(array $user, string $sessionId): void
{
    $limit = plan_limits($user['plan'])['members_per_session'];
    if ($limit < 0) {
        return;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM members WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    if ((int) $stmt->fetchColumn() >= $limit) {
        over_limit('add_member');
    }
}

/** Guard: running an OCR scan. Call before hitting OpenAI. */
function guard_ocr_scan(array $user): void
{
    $limit = plan_limits($user['plan'])['ocr_per_month'];
    if ($limit < 0) {
        return;
    }
    if (ocr_used_this_month((string) $user['id']) >= $limit) {
        over_limit('ocr_scan');
    }
}
