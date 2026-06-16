<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Secret, auth-gated endpoint to set a user's plan — the only way to grant the
 * hidden 'super' tier (never shown in pricing, never purchasable). Gated by the
 * admin_secret from secrets.php, sent as the X-Admin-Secret header.
 *
 *   POST /api/admin_set_plan.php
 *   X-Admin-Secret: <admin_secret>
 *   { "email": "owner@example.com", "plan": "super" }
 */

if (method() !== 'POST') {
    jfail('Method not allowed.', 405);
}

$adminSecret = (string) cfg('admin_secret', '');
$provided = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
if ($adminSecret === '' || !hash_equals($adminSecret, (string) $provided)) {
    jfail('Forbidden.', 403);
}

$in = jin();
$email = strtolower(trim((string) ($in['email'] ?? '')));
$plan = (string) ($in['plan'] ?? '');
if (!in_array($plan, ['free', 'pro', 'super'], true)) {
    jfail('plan must be free, pro, or super.', 422);
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    jfail('No user with that email.', 404);
}

// Super/free have no expiry; pro granted here gets a far-future expiry.
$expires = $plan === 'pro' ? gmdate('Y-m-d H:i:s', time() + 3650 * 86400) : null;
$upd = db()->prepare('UPDATE users SET plan = ?, plan_expires_at = ? WHERE id = ?');
$upd->execute([$plan, $expires, (string) $user['id']]);

jout(['email' => $email, 'plan' => $plan]);
