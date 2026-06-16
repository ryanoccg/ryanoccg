<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Account-level contacts (address book). Reused across every session, so the
 * same person can be searched and added anywhere. "Delete" is a soft delete:
 * the contact is hidden from the list/search but its row stays, so past
 * sessions keep showing the name.
 */

$user = require_user();
$uid = (string) $user['id'];
$id = (string) ($_GET['id'] ?? '');

switch (method()) {
    case 'GET':
        // List active contacts, optional ?q= search (most recent first).
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $stmt = db()->prepare(
                'SELECT id, display_name FROM contacts
                 WHERE owner_user_id = ? AND deleted_at IS NULL AND display_name LIKE ?
                 ORDER BY display_name LIMIT 50'
            );
            $stmt->execute([$uid, '%' . $q . '%']);
        } else {
            $stmt = db()->prepare(
                'SELECT id, display_name FROM contacts
                 WHERE owner_user_id = ? AND deleted_at IS NULL
                 ORDER BY display_name LIMIT 200'
            );
            $stmt->execute([$uid]);
        }
        jout(['contacts' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $in = jin();
        $name = trim((string) ($in['display_name'] ?? ''));
        if ($name === '') {
            jfail('Contact name is required.', 422);
        }
        $cid = find_or_create_contact($uid, $name); // reuses an existing active contact with the same name
        $stmt = db()->prepare('SELECT id, display_name FROM contacts WHERE id = ?');
        $stmt->execute([$cid]);
        jout(['contact' => $stmt->fetch()], 201);
        break;

    case 'PUT':
    case 'PATCH':
        require_owned_contact($id, $uid);
        $in = jin();
        $name = trim((string) ($in['display_name'] ?? ''));
        if ($name === '') {
            jfail('Contact name cannot be empty.', 422);
        }
        $stmt = db()->prepare('UPDATE contacts SET display_name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
        jout(['contact' => ['id' => $id, 'display_name' => $name]]);
        break;

    case 'DELETE':
        require_owned_contact($id, $uid);
        // Soft delete: hide from the address book but keep the row for history.
        $stmt = db()->prepare('UPDATE contacts SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        jout(['deleted' => $id]);
        break;

    default:
        jfail('Method not allowed.', 405);
}
