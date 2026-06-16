<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Stripe webhook: flips users.plan / plan_expires_at on subscription events.
 * Verifies the Stripe-Signature header (HMAC-SHA256) against stripe_webhook_secret.
 * Always 200s after a valid signature so Stripe stops retrying.
 */

$payload = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = (string) cfg('stripe_webhook_secret', '');

if (!verify_stripe_signature($payload, $sigHeader, $secret)) {
    jfail('Invalid signature.', 400);
}

$event = json_decode($payload, true);
$type = $event['type'] ?? '';
$obj = $event['data']['object'] ?? [];

switch ($type) {
    case 'checkout.session.completed':
        $uid = (string) ($obj['client_reference_id'] ?? ($obj['metadata']['user_id'] ?? ''));
        if ($uid !== '') {
            $stmt = db()->prepare(
                'UPDATE users SET plan = "pro", stripe_customer_id = ?, stripe_subscription_id = ? WHERE id = ?'
            );
            $stmt->execute([$obj['customer'] ?? null, $obj['subscription'] ?? null, $uid]);
        }
        break;

    case 'customer.subscription.created':
    case 'customer.subscription.updated':
        $uid = subscription_user_id($obj);
        if ($uid !== '') {
            $status = $obj['status'] ?? '';
            $active = in_array($status, ['active', 'trialing', 'past_due'], true);
            $expires = !empty($obj['current_period_end'])
                ? gmdate('Y-m-d H:i:s', (int) $obj['current_period_end'])
                : null;
            $stmt = db()->prepare(
                'UPDATE users SET plan = ?, plan_expires_at = ?, stripe_subscription_id = ? WHERE id = ? AND plan <> "super"'
            );
            $stmt->execute([$active ? 'pro' : 'free', $active ? $expires : null, $obj['id'] ?? null, $uid]);
        }
        break;

    case 'customer.subscription.deleted':
        $uid = subscription_user_id($obj);
        if ($uid !== '') {
            $stmt = db()->prepare(
                'UPDATE users SET plan = "free", plan_expires_at = NULL WHERE id = ? AND plan <> "super"'
            );
            $stmt->execute([$uid]);
        }
        break;
}

jout(['received' => true]);

function verify_stripe_signature(string $payload, string $header, string $secret): bool
{
    if ($secret === '' || $header === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $header) as $kv) {
        [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
        $parts[$k][] = $v;
    }
    $timestamp = $parts['t'][0] ?? '';
    $signatures = $parts['v1'] ?? [];
    if ($timestamp === '' || !$signatures) {
        return false;
    }
    // Reject events older than 5 minutes (replay protection).
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

/** Resolve the app user from a subscription object (metadata first, then customer id). */
function subscription_user_id(array $sub): string
{
    $uid = (string) ($sub['metadata']['user_id'] ?? '');
    if ($uid !== '') {
        return $uid;
    }
    if (!empty($sub['customer'])) {
        $stmt = db()->prepare('SELECT id FROM users WHERE stripe_customer_id = ?');
        $stmt->execute([$sub['customer']]);
        return (string) ($stmt->fetchColumn() ?: '');
    }
    return '';
}
