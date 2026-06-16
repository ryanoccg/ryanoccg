<?php
declare(strict_types=1);

/**
 * Centralized error logging. Writes PHP warnings/errors/exceptions to a file
 * OUTSIDE the web root (next to uploads, in app_private/logs), and converts
 * uncaught exceptions / fatals into a clean JSON 500 instead of a blank page.
 *
 * Included from bootstrap.php right after config.php so it covers db/auth and
 * every endpoint. Tail the log to diagnose issues:
 *   tail -n 50 ~/app_private/logs/app-error.log
 */

(function (): void {
    // Log dir lives beside the private uploads dir (outside any web root).
    $uploads = (string) cfg('uploads_dir', '');
    $base = $uploads !== '' ? dirname(rtrim($uploads, '/')) : (__DIR__ . '/..');
    $logDir = $base . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }

    ini_set('display_errors', '0');     // never leak errors to the API response
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/app-error.log');
    }
})();

set_exception_handler(function (Throwable $e): void {
    error_log('[uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Server error.']);
    exit;
});

register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[fatal] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Server error.']);
        }
    }
});
