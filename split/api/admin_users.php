<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Admin-only: list users with their plan + this-month usage, and change a plan.
$admin = require_admin();
$period = current_period();

switch (method()) {
    case 'GET':
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql =
            'SELECT u.id, u.email, u.plan, u.plan_expires_at, u.is_admin, u.created_at,
                    (SELECT COUNT(*) FROM sessions s WHERE s.owner_user_id = u.id) AS sessions,
                    COALESCE(uc.ocr_scans, 0)  AS ocr_scans,
                    COALESCE(uc.ocr_tokens, 0) AS ocr_tokens
             FROM users u
             LEFT JOIN usage_counters uc ON uc.user_id = u.id AND uc.period = ?';
        $args = [$period];
        if ($q !== '') {
            $sql .= ' WHERE u.email LIKE ?';
            $args[] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY u.created_at DESC LIMIT 300';
        $stmt = db()->prepare($sql);
        $stmt->execute($args);
        $rows = array_map(static function (array $r): array {
            return [
                'id'              => $r['id'],
                'email'           => $r['email'],
                'plan'            => $r['plan'],
                'plan_expires_at' => $r['plan_expires_at'],
                'is_admin'        => (int) $r['is_admin'],
                'created_at'      => $r['created_at'],
                'sessions'        => (int) $r['sessions'],
                'ocr_scans'       => (int) $r['ocr_scans'],
                'ocr_tokens'      => (int) $r['ocr_tokens'],
            ];
        }, $stmt->fetchAll());
        jout(['users' => $rows, 'period' => $period]);
        break;

    case 'PUT':
    case 'PATCH':
        $id = (string) ($_GET['id'] ?? '');
        $in = jin();
        $plan = (string) ($in['plan'] ?? '');
        if (!in_array($plan, ['free', 'pro', 'super'], true)) {
            jfail('plan must be free, pro, or super.', 422);
        }
        $chk = db()->prepare('SELECT id FROM users WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            jfail('User not found.', 404);
        }
        // super/free have no expiry; pro granted here gets a far-future expiry.
        $expires = $plan === 'pro' ? gmdate('Y-m-d H:i:s', time() + 3650 * 86400) : null;
        $upd = db()->prepare('UPDATE users SET plan = ?, plan_expires_at = ? WHERE id = ?');
        $upd->execute([$plan, $expires, $id]);
        jout(['id' => $id, 'plan' => $plan]);
        break;

    default:
        jfail('Method not allowed.', 405);
}
