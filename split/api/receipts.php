<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$uid = (string) $user['id'];
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';

switch (method()) {
    case 'GET':
        get_receipt($id, $uid);
        break;
    case 'POST':
        save_receipt('', $uid);
        break;
    case 'PUT':
    case 'PATCH':
        save_receipt($id, $uid);
        break;
    case 'DELETE':
        require_owned_receipt($id, $uid);
        $stmt = db()->prepare('DELETE FROM receipts WHERE id = ?');
        $stmt->execute([$id]);
        jout(['deleted' => $id]);
        break;
    default:
        jfail('Method not allowed.', 405);
}

function get_receipt(string $id, string $uid): void
{
    $receipt = require_owned_receipt($id, $uid);
    $li = db()->prepare('SELECT id, name, quantity, unit_price, total, sort_order FROM line_items WHERE receipt_id = ? ORDER BY sort_order, id');
    $li->execute([$id]);
    $items = $li->fetchAll();
    foreach ($items as &$item) {
        $sh = db()->prepare('SELECT id, member_id, weight FROM item_shares WHERE line_item_id = ?');
        $sh->execute([$item['id']]);
        $item['shares'] = $sh->fetchAll();
    }
    unset($item);
    $receipt['line_items'] = $items;
    jout(['receipt' => $receipt]);
}

/**
 * Create ($id = '') or replace ($id set) a receipt and its full line-item /
 * share tree in one transaction. Body shape:
 *   { session_id, merchant, currency, subtotal, tax, tip, total,
 *     paid_by_member_id, image_path?,
 *     line_items: [ { name, quantity, unit_price, total,
 *                     shares: [ { member_id, weight } ] } ] }
 */
function save_receipt(string $id, string $uid): void
{
    $in = jin();
    $sessionId = (string) ($in['session_id'] ?? '');
    $session = require_owned_session($sessionId, $uid);

    if ($id !== '') {
        $existing = require_owned_receipt($id, $uid);
        if ((string) $existing['session_id'] !== $sessionId) {
            jfail('Receipt does not belong to that session.', 422);
        }
    }

    // Valid member ids for this session (for payer + share validation).
    $mStmt = db()->prepare('SELECT id FROM members WHERE session_id = ?');
    $mStmt->execute([$sessionId]);
    $validSet = array_flip(array_column($mStmt->fetchAll(), 'id'));

    $merchant = trim((string) ($in['merchant'] ?? '')) ?: null;
    $currency = strtoupper(trim((string) ($in['currency'] ?? $session['currency'])));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = $session['currency'];
    }
    $subtotal = round((float) ($in['subtotal'] ?? 0), 2);
    $tax = round((float) ($in['tax'] ?? 0), 2);
    $tip = round((float) ($in['tip'] ?? 0), 2);
    $total = round((float) ($in['total'] ?? 0), 2);
    $imagePath = isset($in['image_path']) ? (string) $in['image_path'] : ($id !== '' ? ($existing['image_path'] ?? null) : null);

    $payer = $in['paid_by_member_id'] ?? null;
    $payer = ($payer === null || $payer === '') ? null : (string) $payer;
    if ($payer !== null && !isset($validSet[$payer])) {
        jfail('Payer must be a member of this session.', 422);
    }

    $lineItems = is_array($in['line_items'] ?? null) ? $in['line_items'] : [];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id === '') {
            $id = uuidv4();
            $stmt = $pdo->prepare(
                'INSERT INTO receipts (id, session_id, image_path, merchant, currency, subtotal, tax, tip, total, paid_by_member_id, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "ready")'
            );
            $stmt->execute([$id, $sessionId, $imagePath, $merchant, $currency, $subtotal, $tax, $tip, $total, $payer]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE receipts SET image_path = ?, merchant = ?, currency = ?, subtotal = ?, tax = ?, tip = ?, total = ?, paid_by_member_id = ?, status = "ready"
                 WHERE id = ?'
            );
            $stmt->execute([$imagePath, $merchant, $currency, $subtotal, $tax, $tip, $total, $payer, $id]);
            // Replace children.
            $del = $pdo->prepare('DELETE FROM line_items WHERE receipt_id = ?'); // cascades to item_shares
            $del->execute([$id]);
        }

        $insItem = $pdo->prepare(
            'INSERT INTO line_items (id, receipt_id, name, quantity, unit_price, total, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insShare = $pdo->prepare(
            'INSERT INTO item_shares (id, line_item_id, member_id, weight) VALUES (?, ?, ?, ?)'
        );

        $order = 0;
        foreach ($lineItems as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = round((float) ($item['quantity'] ?? 1), 3);
            $unit = round((float) ($item['unit_price'] ?? 0), 2);
            $itemTotal = round((float) ($item['total'] ?? ($qty * $unit)), 2);
            $lineItemId = uuidv4();
            $insItem->execute([$lineItemId, $id, $name, $qty, $unit, $itemTotal, $order++]);

            $shares = is_array($item['shares'] ?? null) ? $item['shares'] : [];
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
                $insShare->execute([uuidv4(), $lineItemId, $memberId, round($weight, 3)]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jfail('Could not save receipt.', 500);
    }

    get_receipt($id, $uid);
}
