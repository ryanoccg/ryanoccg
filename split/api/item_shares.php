<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$uid = (int) $user['id'];

// Replace the full set of shares for one line item (powers the assignment UI:
// the client sends the complete member set after each toggle).
if (method() !== 'PUT' && method() !== 'POST') {
    jfail('Method not allowed.', 405);
}

$lineItemId = (int) ($_GET['line_item_id'] ?? 0);
$item = require_owned_line_item($lineItemId, $uid);

$in = jin();
$shares = is_array($in['shares'] ?? null) ? $in['shares'] : [];

// Members valid for this line item's session.
$mStmt = db()->prepare(
    'SELECT m.id FROM members m
     JOIN receipts r ON r.session_id = m.session_id
     WHERE r.id = ?'
);
$mStmt->execute([(int) $item['receipt_id']]);
$validSet = array_flip(array_map('intval', array_column($mStmt->fetchAll(), 'id')));

$pdo = db();
$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM item_shares WHERE line_item_id = ?');
    $del->execute([$lineItemId]);

    $ins = $pdo->prepare('INSERT INTO item_shares (line_item_id, member_id, weight) VALUES (?, ?, ?)');
    $seen = [];
    foreach ($shares as $share) {
        $memberId = (int) ($share['member_id'] ?? 0);
        if (!isset($validSet[$memberId]) || isset($seen[$memberId])) {
            continue;
        }
        $seen[$memberId] = true;
        $weight = (float) ($share['weight'] ?? 1);
        if ($weight <= 0) {
            $weight = 1;
        }
        $ins->execute([$lineItemId, $memberId, round($weight, 3)]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    jfail('Could not update shares.', 500);
}

$sh = db()->prepare('SELECT id, member_id, weight FROM item_shares WHERE line_item_id = ?');
$sh->execute([$lineItemId]);
jout(['line_item_id' => $lineItemId, 'shares' => $sh->fetchAll()]);

function require_owned_line_item(int $lineItemId, int $uid): array
{
    $stmt = db()->prepare(
        'SELECT li.* FROM line_items li
         JOIN receipts r ON r.id = li.receipt_id
         JOIN sessions s ON s.id = r.session_id
         WHERE li.id = ? AND s.owner_user_id = ?'
    );
    $stmt->execute([$lineItemId, $uid]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Line item not found.', 404);
    }
    return $row;
}
