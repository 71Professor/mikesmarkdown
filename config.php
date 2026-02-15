<?php
/**
 * Database configuration for HedgeDoc Notes
 * Reads credentials from .env file (not accessible via browser)
 */

$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    http_response_code(500);
    die('Missing .env file. Copy .env.example to .env and fill in your database credentials.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Logging configuration
define('LOGGING_ENABLED', $_ENV['LOGGING_ENABLED'] ?? true);
define('LOG_DIR', $_ENV['LOG_DIR'] ?? __DIR__ . '/logs');
define('LOG_RETENTION_DAYS', $_ENV['LOG_RETENTION_DAYS'] ?? 30);

// Include logger
require_once __DIR__ . '/logger.php';
