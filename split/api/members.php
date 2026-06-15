<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$uid = (string) $user['id'];

switch (method()) {
    case 'GET':
        $sessionId = (string) ($_GET['session_id'] ?? '');
        require_owned_session($sessionId, $uid);
        $stmt = db()->prepare('SELECT id, display_name, linked_user_id FROM members WHERE session_id = ? ORDER BY display_name, id');
        $stmt->execute([$sessionId]);
        jout(['members' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $in = jin();
        $sessionId = (string) ($in['session_id'] ?? '');
        $name = trim((string) ($in['display_name'] ?? ''));
        require_owned_session($sessionId, $uid);
        if ($name === '') {
            jfail('Member name is required.', 422);
        }
        guard_add_member($user, $sessionId);
        $id = uuidv4();
        $stmt = db()->prepare('INSERT INTO members (id, session_id, display_name) VALUES (?, ?, ?)');
        $stmt->execute([$id, $sessionId, $name]);
        jout(['member' => ['id' => $id, 'session_id' => $sessionId, 'display_name' => $name]], 201);
        break;

    case 'PUT':
    case 'PATCH':
        $id = (string) ($_GET['id'] ?? '');
        $in = jin();
        $name = trim((string) ($in['display_name'] ?? ''));
        require_owned_member($id, $uid);
        if ($name === '') {
            jfail('Member name cannot be empty.', 422);
        }
        $stmt = db()->prepare('UPDATE members SET display_name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
        jout(['member' => ['id' => $id, 'display_name' => $name]]);
        break;

    case 'DELETE':
        $id = (string) ($_GET['id'] ?? '');
        require_owned_member($id, $uid);
        // Refuse if they paid for receipts — deleting would nullify the payer and
        // silently drop those receipts from settlement. Make the user reassign first.
        $chk = db()->prepare('SELECT COUNT(*) FROM receipts WHERE paid_by_member_id = ?');
        $chk->execute([$id]);
        if ((int) $chk->fetchColumn() > 0) {
            jfail('This member paid for one or more receipts. Change those payers before removing them.', 409);
        }
        $stmt = db()->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$id]);
        jout(['deleted' => $id]);
        break;

    default:
        jfail('Method not allowed.', 405);
}
