<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$uid = (string) $user['id'];

// Replace the full set of shares for one line item (powers the assignment UI:
// the client sends the complete member set after each toggle).
if (method() !== 'PUT' && method() !== 'POST') {
    jfail('Method not allowed.', 405);
}

$lineItemId = (string) ($_GET['line_item_id'] ?? '');
$item = require_owned_line_item($lineItemId, $uid);

$in = jin();
$shares = is_array($in['shares'] ?? null) ? $in['shares'] : [];

// Members valid for this line item's session.
$mStmt = db()->prepare(
    'SELECT m.id FROM members m
     JOIN receipts r ON r.session_id = m.session_id
     WHERE r.id = ?'
);
$mStmt->execute([(string) $item['receipt_id']]);
$validSet = array_flip(array_column($mStmt->fetchAll(), 'id'));

$pdo = db();
$pdo->beginTransaction();
try {
    replace_item_shares($pdo, $lineItemId, $shares, $validSet);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    jfail('Could not update shares.', 500);
}

$sh = db()->prepare('SELECT id, member_id, weight FROM item_shares WHERE line_item_id = ?');
$sh->execute([$lineItemId]);
jout(['line_item_id' => $lineItemId, 'shares' => $sh->fetchAll()]);
