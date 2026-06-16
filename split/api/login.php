<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (method() !== 'POST') {
    jfail('Method not allowed.', 405);
}

$in = jin();
$email = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');

if ($email === '' || $password === '') {
    jfail('Email and password are required.', 422);
}

$stmt = db()->prepare('SELECT id, email, password_hash, plan FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jfail('Invalid email or password.', 401);
}

$token = jwt_encode(['sub' => (string) $user['id'], 'email' => $user['email']]);
jout([
    'token' => $token,
    'user'  => ['id' => (string) $user['id'], 'email' => $user['email'], 'plan' => $user['plan']],
]);
