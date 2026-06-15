<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$uid = (int) $user['id'];

switch (method()) {
    case 'GET':
        $sessionId = (int) ($_GET['session_id'] ?? 0);
        require_owned_session($sessionId, $uid);
        $stmt = db()->prepare('SELECT id, display_name, linked_user_id FROM members WHERE session_id = ? ORDER BY id');
        $stmt->execute([$sessionId]);
        jout(['members' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $in = jin();
        $sessionId = (int) ($in['session_id'] ?? 0);
        $name = trim((string) ($in['display_name'] ?? ''));
        require_owned_session($sessionId, $uid);
        if ($name === '') {
            jfail('Member name is required.', 422);
        }
        guard_add_member($user, $sessionId);
        $stmt = db()->prepare('INSERT INTO members (session_id, display_name) VALUES (?, ?)');
        $stmt->execute([$sessionId, $name]);
        $id = (int) db()->lastInsertId();
        jout(['member' => ['id' => $id, 'session_id' => $sessionId, 'display_name' => $name]], 201);
        break;

    case 'PUT':
    case 'PATCH':
        $id = (int) ($_GET['id'] ?? 0);
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
        $id = (int) ($_GET['id'] ?? 0);
        require_owned_member($id, $uid);
        $stmt = db()->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$id]);
        jout(['deleted' => $id]);
        break;

    default:
        jfail('Method not allowed.', 405);
}

function require_owned_member(int $memberId, int $uid): array
{
    $stmt = db()->prepare(
        'SELECT m.* FROM members m
         JOIN sessions s ON s.id = m.session_id
         WHERE m.id = ? AND s.owner_user_id = ?'
    );
    $stmt->execute([$memberId, $uid]);
    $row = $stmt->fetch();
    if (!$row) {
        jfail('Member not found.', 404);
    }
    return $row;
}
