<?php
declare(strict_types=1);

/** Shared PDO connection (lazy singleton). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db   = cfg('db', []);
    $host = $db['host'] ?? 'localhost';
    $name = $db['name'] ?? '';
    $user = $db['user'] ?? '';
    $pass = $db['pass'] ?? '';
    $charset = $db['charset'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }
    return $pdo;
}
