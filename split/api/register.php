<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (method() !== 'POST') {
    jfail('Method not allowed.', 405);
}

$in = jin();
$email = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jfail('A valid email is required.', 422);
}
if (strlen($password) < 8) {
    jfail('Password must be at least 8 characters.', 422);
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jfail('That email is already registered.', 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = db()->prepare('INSERT INTO users (email, password_hash, plan) VALUES (?, ?, ?)');
$stmt->execute([$email, $hash, 'free']);
$userId = (int) db()->lastInsertId();

$token = jwt_encode(['sub' => $userId, 'email' => $email]);
jout([
    'token' => $token,
    'user'  => ['id' => $userId, 'email' => $email, 'plan' => 'free'],
], 201);
