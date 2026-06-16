<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Session membership = linking a contact into a session. Add by an existing
 * contact_id (picked from search) or by display_name (quick-add, which finds or
 * creates the contact). Renaming is done on the contact (contacts.php), so the
 * name propagates to every session.
 */

$user = require_user();
$uid = (string) $user['id'];

switch (method()) {
    case 'GET':
        $sessionId = (string) ($_GET['session_id'] ?? '');
        require_owned_session($sessionId, $uid);
        jout(['members' => session_members($sessionId)]);
        break;

    case 'POST':
        $in = jin();
        $sessionId = (string) ($in['session_id'] ?? '');
        require_owned_session($sessionId, $uid);
        guard_add_member($user, $sessionId);

        // Resolve which contact to link.
        $contactId = trim((string) ($in['contact_id'] ?? ''));
        if ($contactId !== '') {
            $contact = require_owned_contact($contactId, $uid);
            if (!empty($contact['deleted_at'])) {
                jfail('That contact has been deleted.', 422);
            }
        } else {
            $name = trim((string) ($in['display_name'] ?? ''));
            if ($name === '') {
                jfail('Pick a contact or enter a name.', 422);
            }
            $contactId = find_or_create_contact($uid, $name);
        }

        // Already in this session? Return the existing membership idempotently.
        $existing = db()->prepare('SELECT id FROM members WHERE session_id = ? AND contact_id = ?');
        $existing->execute([$sessionId, $contactId]);
        $memberId = (string) ($existing->fetchColumn() ?: '');
        if ($memberId === '') {
            $memberId = uuidv4();
            $ins = db()->prepare('INSERT INTO members (id, session_id, contact_id) VALUES (?, ?, ?)');
            $ins->execute([$memberId, $sessionId, $contactId]);
        }

        $m = db()->prepare(
            'SELECT m.id, m.contact_id, c.display_name
             FROM members m JOIN contacts c ON c.id = m.contact_id WHERE m.id = ?'
        );
        $m->execute([$memberId]);
        jout(['member' => $m->fetch()], 201);
        break;

    case 'DELETE':
        $id = (string) ($_GET['id'] ?? '');
        require_owned_member($id, $uid);
        // Refuse if they paid for receipts — removing would nullify the payer and
        // silently drop those receipts from settlement. Make the user reassign first.
        $chk = db()->prepare('SELECT COUNT(*) FROM receipts WHERE paid_by_member_id = ?');
        $chk->execute([$id]);
        if ((int) $chk->fetchColumn() > 0) {
            jfail('This person paid for one or more receipts. Change those payers before removing them.', 409);
        }
        $stmt = db()->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$id]);
        jout(['deleted' => $id]);
        break;

    default:
        jfail('Method not allowed.', 405);
}

/** Members of a session with their contact display name. */
function session_members(string $sessionId): array
{
    $stmt = db()->prepare(
        'SELECT m.id, m.contact_id, c.display_name
         FROM members m JOIN contacts c ON c.id = m.contact_id
         WHERE m.session_id = ? ORDER BY c.display_name, m.id'
    );
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}
