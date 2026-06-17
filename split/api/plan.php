<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
if (method() !== 'GET') {
    jfail('Method not allowed.', 405);
}

$plan = $user['plan'];
$limits = plan_limits($plan);

$stmt = db()->prepare('SELECT COUNT(*) FROM sessions WHERE owner_user_id = ?');
$stmt->execute([(string) $user['id']]);
$sessionCount = (int) $stmt->fetchColumn();

jout([
    'user'             => ['id' => (string) $user['id'], 'email' => $user['email'], 'plan' => $plan],
    'plan'             => $plan,
    'plan_expires_at'  => $user['plan_expires_at'] ?? null,
    'ads'              => ads_enabled($plan),
    'limits'           => $limits,
    'usage'            => [
        'sessions'   => $sessionCount,
        'ocr_scans'  => ocr_used_this_month((string) $user['id']),
        'ocr_tokens' => ocr_tokens_this_month((string) $user['id']),
        'period'     => current_period(),
    ],
]);
