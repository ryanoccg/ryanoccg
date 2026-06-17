<?php
declare(strict_types=1);

/**
 * CLI-only database migration runner.
 *
 * Applies any split/api/migrations/*.sql files (in filename order) that haven't
 * already been recorded in the schema_migrations table. Safe to run repeatedly
 * — already-applied (and "already exists") migrations are skipped.
 *
 * The deploy workflow runs this automatically after deploying api/. To run by
 * hand on the server:   php api/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("migrate.php is CLI-only.\n");
}

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = array_flip($pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

// MySQL/MariaDB "already exists / nothing to drop" codes — treat as satisfied so
// the same change applied via schema.sql on a fresh install isn't a hard error.
$benign = ['1050', '1060', '1061', '1062', '1091'];

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }
    try {
        $pdo->exec((string) file_get_contents($file));
        echo "applied: $name\n";
    } catch (PDOException $e) {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());
        if (in_array($code, $benign, true)) {
            echo "already satisfied, recording: $name ({$e->getMessage()})\n";
        } else {
            fwrite(STDERR, "MIGRATION FAILED: $name — {$e->getMessage()}\n");
            exit(1);
        }
    }
    $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
    $count++;
}

echo $count ? "$count migration(s) processed.\n" : "No new migrations.\n";
