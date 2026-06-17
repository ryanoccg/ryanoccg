<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Admin-only: high-level usage stats for the whole app.
require_admin();
if (method() !== 'GET') {
    jfail('Method not allowed.', 405);
}

$pdo = db();
$period = current_period();

$users = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(plan = 'free')  AS free,
        SUM(plan = 'pro')   AS pro,
        SUM(plan = 'super') AS super
     FROM users"
)->fetch();

$month = $pdo->prepare(
    'SELECT COALESCE(SUM(ocr_scans),0) AS scans, COALESCE(SUM(ocr_tokens),0) AS tokens
     FROM usage_counters WHERE period = ?'
);
$month->execute([$period]);
$m = $month->fetch();

jout([
    'period'   => $period,
    'users'    => [
        'total' => (int) ($users['total'] ?? 0),
        'free'  => (int) ($users['free'] ?? 0),
        'pro'   => (int) ($users['pro'] ?? 0),
        'super' => (int) ($users['super'] ?? 0),
    ],
    'sessions' => (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(),
    'receipts' => (int) $pdo->query('SELECT COUNT(*) FROM receipts')->fetchColumn(),
    'contacts' => (int) $pdo->query('SELECT COUNT(*) FROM contacts WHERE deleted_at IS NULL')->fetchColumn(),
    'this_month' => [
        'ocr_scans'  => (int) ($m['scans'] ?? 0),
        'ocr_tokens' => (int) ($m['tokens'] ?? 0),
    ],
]);
