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
        $receipt = require_owned_receipt($id, $uid);
        $stmt = db()->prepare('DELETE FROM receipts WHERE id = ?');
        $stmt->execute([$id]);
        delete_upload($receipt['image_path'] ?? null); // don't leave the photo on disk
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

    $oldImage = null;
    if ($id !== '') {
        $existing = require_owned_receipt($id, $uid);
        if ((string) $existing['session_id'] !== $sessionId) {
            jfail('Receipt does not belong to that session.', 422);
        }
        $oldImage = $existing['image_path'] ?? null;
    }

    // Keep only items that carry a name; a receipt must have at least one.
    $lineItems = is_array($in['line_items'] ?? null) ? $in['line_items'] : [];
    $lineItems = array_values(array_filter($lineItems, fn($it) => trim((string) ($it['name'] ?? '')) !== ''));
    if (!$lineItems) {
        jfail('A receipt needs at least one line item.', 422);
    }

    $validSet = session_member_id_set($sessionId);

    $merchant = trim((string) ($in['merchant'] ?? '')) ?: null;
    $currency = normalize_currency($in['currency'] ?? null, $session['currency']);
    $subtotal = round((float) ($in['subtotal'] ?? 0), 2);
    $tax = round((float) ($in['tax'] ?? 0), 2);
    $tip = round((float) ($in['tip'] ?? 0), 2);
    $total = round((float) ($in['total'] ?? 0), 2);

    // image_path is client-supplied: accept only our own generated filename.
    $imagePath = $oldImage;
    if (isset($in['image_path']) && valid_upload_name((string) $in['image_path'])) {
        $imagePath = (string) $in['image_path'];
    }

    $payer = $in['paid_by_member_id'] ?? null;
    $payer = ($payer === null || $payer === '') ? null : (string) $payer;
    if ($payer !== null && !isset($validSet[$payer])) {
        jfail('Payer must be a member of this session.', 422);
    }

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

        $order = 0;
        foreach ($lineItems as $item) {
            $name = trim((string) $item['name']);
            $qty = round((float) ($item['quantity'] ?? 1), 3);
            $unit = round((float) ($item['unit_price'] ?? 0), 2);
            $itemTotal = round((float) ($item['total'] ?? ($qty * $unit)), 2);
            $lineItemId = uuidv4();
            $insItem->execute([$lineItemId, $id, $name, $qty, $unit, $itemTotal, $order++]);

            $shares = is_array($item['shares'] ?? null) ? $item['shares'] : [];
            replace_item_shares($pdo, $lineItemId, $shares, $validSet);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jfail('Could not save receipt.', 500);
    }

    // If the photo was swapped out, remove the old file from disk.
    if ($oldImage && $oldImage !== $imagePath) {
        delete_upload($oldImage);
    }

    get_receipt($id, $uid);
}
