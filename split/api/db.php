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

/** Generate a random UUIDv4 string (application-side primary keys). */
function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
