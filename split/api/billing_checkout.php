<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Create a Stripe Checkout Session (subscription) for the Pro plan and return
 * its URL. The webhook (stripe_webhook.php) flips the user's plan once payment
 * completes. user_id travels on client_reference_id + subscription metadata so
 * the webhook can map events back to the account.
 */

$user = require_user();
if (method() !== 'POST') {
    jfail('Method not allowed.', 405);
}
if ($user['plan'] === 'super') {
    jfail('Your account already has unlimited access.', 400);
}

$secret = (string) cfg('stripe_secret_key', '');
$price  = (string) cfg('stripe_price_pro', '');
$appUrl = rtrim((string) cfg('app_url', ''), '/');
if ($secret === '' || $price === '') {
    jfail('Billing is not configured.', 500);
}

$params = [
    'mode' => 'subscription',
    'line_items' => [['price' => $price, 'quantity' => 1]],
    'client_reference_id' => (string) $user['id'],
    'customer_email' => $user['email'],
    'subscription_data' => ['metadata' => ['user_id' => (string) $user['id']]],
    'metadata' => ['user_id' => (string) $user['id']],
    'success_url' => $appUrl . '/account?upgrade=success',
    'cancel_url'  => $appUrl . '/pricing?upgrade=cancelled',
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => $secret . ':',
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_TIMEOUT => 30,
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = is_string($resp) ? json_decode($resp, true) : null;
if ($code < 200 || $code >= 300 || empty($json['url'])) {
    jfail('Could not start checkout.', 502, ['stripe' => $json['error']['message'] ?? null]);
}

jout(['url' => $json['url']]);
