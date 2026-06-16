<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Granular single line-item ops (the bulk path is receipts.php). Useful for
// incremental edits on the review screen.
$user = require_user();
$uid = (string) $user['id'];
$id = (string) ($_GET['id'] ?? '');

switch (method()) {
    case 'POST':
        $in = jin();
        $receiptId = (string) ($in['receipt_id'] ?? '');
        require_owned_receipt($receiptId, $uid);
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            jfail('Item name is required.', 422);
        }
        $qty = round((float) ($in['quantity'] ?? 1), 3);
        $unit = round((float) ($in['unit_price'] ?? 0), 2);
        $total = round((float) ($in['total'] ?? ($qty * $unit)), 2);
        $order = (int) ($in['sort_order'] ?? 0);
        $newId = uuidv4();
        $stmt = db()->prepare('INSERT INTO line_items (id, receipt_id, name, quantity, unit_price, total, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$newId, $receiptId, $name, $qty, $unit, $total, $order]);
        jout(['line_item' => ['id' => $newId, 'receipt_id' => $receiptId, 'name' => $name, 'quantity' => $qty, 'unit_price' => $unit, 'total' => $total, 'sort_order' => $order]], 201);
        break;

    case 'PUT':
    case 'PATCH':
        $item = require_owned_line_item($id, $uid);
        $in = jin();
        $name = isset($in['name']) ? trim((string) $in['name']) : $item['name'];
        $qty = isset($in['quantity']) ? round((float) $in['quantity'], 3) : (float) $item['quantity'];
        $unit = isset($in['unit_price']) ? round((float) $in['unit_price'], 2) : (float) $item['unit_price'];
        $total = isset($in['total']) ? round((float) $in['total'], 2) : (float) $item['total'];
        if ($name === '') {
            jfail('Item name cannot be empty.', 422);
        }
        $stmt = db()->prepare('UPDATE line_items SET name = ?, quantity = ?, unit_price = ?, total = ? WHERE id = ?');
        $stmt->execute([$name, $qty, $unit, $total, $id]);
        jout(['line_item' => ['id' => $id, 'name' => $name, 'quantity' => $qty, 'unit_price' => $unit, 'total' => $total]]);
        break;

    case 'DELETE':
        require_owned_line_item($id, $uid);
        $stmt = db()->prepare('DELETE FROM line_items WHERE id = ?');
        $stmt->execute([$id]);
        jout(['deleted' => $id]);
        break;

    default:
        jfail('Method not allowed.', 405);
}
