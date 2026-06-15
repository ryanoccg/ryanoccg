<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$uid = (int) $user['id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

switch (method()) {
    case 'GET':
        $id ? get_session_detail($id, $uid) : list_sessions($uid);
        break;
    case 'POST':
        create_session($user);
        break;
    case 'PUT':
    case 'PATCH':
        update_session($id, $uid);
        break;
    case 'DELETE':
        delete_session($id, $uid);
        break;
    default:
        jfail('Method not allowed.', 405);
}

function list_sessions(int $uid): void
{
    $stmt = db()->prepare(
        'SELECT s.id, s.name, s.currency, s.created_at,
                (SELECT COUNT(*) FROM members m WHERE m.session_id = s.id)  AS member_count,
                (SELECT COUNT(*) FROM receipts r WHERE r.session_id = s.id) AS receipt_count
         FROM sessions s
         WHERE s.owner_user_id = ?
         ORDER BY s.created_at DESC'
    );
    $stmt->execute([$uid]);
    jout(['sessions' => $stmt->fetchAll()]);
}

/** Full nested tree: session + members + receipts(+ line_items(+ shares)). */
function get_session_detail(int $id, int $uid): void
{
    $session = require_owned_session($id, $uid);

    $m = db()->prepare('SELECT id, display_name, linked_user_id FROM members WHERE session_id = ? ORDER BY id');
    $m->execute([$id]);
    $members = $m->fetchAll();

    $r = db()->prepare(
        'SELECT id, merchant, currency, subtotal, tax, tip, total, paid_by_member_id, status, image_path, created_at
         FROM receipts WHERE session_id = ? ORDER BY created_at, id'
    );
    $r->execute([$id]);
    $receipts = $r->fetchAll();

    if ($receipts) {
        $receiptIds = array_column($receipts, 'id');
        $in = implode(',', array_fill(0, count($receiptIds), '?'));

        $li = db()->prepare(
            "SELECT id, receipt_id, name, quantity, unit_price, total, sort_order
             FROM line_items WHERE receipt_id IN ($in) ORDER BY receipt_id, sort_order, id"
        );
        $li->execute($receiptIds);
        $lineItems = $li->fetchAll();

        $byReceipt = [];
        $itemIndex = [];
        foreach ($lineItems as &$item) {
            $item['shares'] = [];
            $byReceipt[$item['receipt_id']][] = &$item;
            $itemIndex[$item['id']] = &$item;
        }
        unset($item);

        if ($lineItems) {
            $itemIds = array_column($lineItems, 'id');
            $in2 = implode(',', array_fill(0, count($itemIds), '?'));
            $sh = db()->prepare(
                "SELECT id, line_item_id, member_id, weight FROM item_shares WHERE line_item_id IN ($in2)"
            );
            $sh->execute($itemIds);
            foreach ($sh->fetchAll() as $share) {
                if (isset($itemIndex[$share['line_item_id']])) {
                    $itemIndex[$share['line_item_id']]['shares'][] = $share;
                }
            }
        }

        foreach ($receipts as &$receipt) {
            $receipt['line_items'] = $byReceipt[$receipt['id']] ?? [];
        }
        unset($receipt);
    }

    $session['members'] = $members;
    $session['receipts'] = $receipts;
    jout(['session' => $session]);
}

function create_session(array $user): void
{
    guard_create_session($user);
    $in = jin();
    $name = trim((string) ($in['name'] ?? ''));
    $currency = strtoupper(trim((string) ($in['currency'] ?? 'USD')));
    if ($name === '') {
        jfail('Session name is required.', 422);
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = 'USD';
    }
    $stmt = db()->prepare('INSERT INTO sessions (owner_user_id, name, currency) VALUES (?, ?, ?)');
    $stmt->execute([(int) $user['id'], $name, $currency]);
    $id = (int) db()->lastInsertId();

    // Seed any members provided at creation (each subject to the member guard).
    if (!empty($in['members']) && is_array($in['members'])) {
        foreach ($in['members'] as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            guard_add_member($user, $id);
            $ms = db()->prepare('INSERT INTO members (session_id, display_name) VALUES (?, ?)');
            $ms->execute([$id, $name]);
        }
    }

    get_session_detail($id, (int) $user['id']);
}

function update_session(int $id, int $uid): void
{
    require_owned_session($id, $uid);
    $in = jin();
    $fields = [];
    $args = [];
    if (isset($in['name'])) {
        $name = trim((string) $in['name']);
        if ($name === '') {
            jfail('Session name cannot be empty.', 422);
        }
        $fields[] = 'name = ?';
        $args[] = $name;
    }
    if (isset($in['currency'])) {
        $cur = strtoupper(trim((string) $in['currency']));
        if (preg_match('/^[A-Z]{3}$/', $cur)) {
            $fields[] = 'currency = ?';
            $args[] = $cur;
        }
    }
    if ($fields) {
        $args[] = $id;
        $stmt = db()->prepare('UPDATE sessions SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($args);
    }
    get_session_detail($id, $uid);
}

function delete_session(int $id, int $uid): void
{
    require_owned_session($id, $uid);
    $stmt = db()->prepare('DELETE FROM sessions WHERE id = ?');
    $stmt->execute([$id]); // cascades to members/receipts/line_items/shares
    jout(['deleted' => $id]);
}
