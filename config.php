<?php
/**
 * Database configuration for HedgeDoc Notes
 * Reads credentials from .env file (not accessible via browser)
 */

/**
 * Validate config.php access - Application-level protection for .env file
 *
 * Implements defense-in-depth by validating execution context before loading .env.
 * This complements .htaccess protections and provides security even if web server
 * configuration is misconfigured or bypassed.
 */
function validateConfigAccess(): void
{
    // Define authorized files (hardcoded for security)
    $authorizedFiles = [
        __DIR__ . '/api.php',
        __DIR__ . '/setup.php',
    ];

    // Layer 1: Detect direct execution
    $currentFile = realpath(__FILE__);
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : '';

    if ($currentFile === $scriptFile) {
        logSecurityViolation('direct_execution', [
            'violation' => 'config.php executed directly',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
        ]);
        http_response_code(403);
        die('Access denied');
    }

    // Layer 2: Validate including script against whitelist
    // Get full backtrace to find the actual file that included config.php
    // backtrace[0] is the call to validateConfigAccess() from config.php
    // backtrace[1] is the require/include from the actual including file
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $includingFile = '';

    // Find the first file in backtrace that is not config.php
    foreach ($backtrace as $trace) {
        if (isset($trace['file']) && realpath($trace['file']) !== $currentFile) {
            $includingFile = realpath($trace['file']);
            break;
        }
    }

    if ($includingFile) {
        $isAuthorized = false;
        foreach ($authorizedFiles as $authorized) {
            // Normalize paths for comparison (handle symlinks and case sensitivity)
            if (strtolower($includingFile) === strtolower(realpath($authorized))) {
                $isAuthorized = true;
                break;
            }
        }

        if (!$isAuthorized) {
            logSecurityViolation('unauthorized_include', [
                'violation' => 'config.php included from unauthorized file',
                'including_file' => $includingFile,
                'authorized_files' => implode(', ', $authorizedFiles),
            ]);
            http_response_code(403);
            die('Access denied');
        }
    }

    // Layer 3: HTTP context validation
    $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server';

    if (!$isCli && $scriptFile && basename($scriptFile) === 'config.php') {
        logSecurityViolation('http_access', [
            'violation' => 'HTTP request directly to config.php',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * Log security violation with context
 *
 * Uses inline logging since Logger class is not yet available
 * (logger.php is included after config.php loads)
 */
function logSecurityViolation(string $violationType, array $context): void
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $logFile = $logDir . '/app-' . gmdate('Y-m-d') . '.log';

    $contextData = array_merge([
        'violation_type' => $violationType,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => gmdate('Y-m-d H:i:s'),
    ], $context);

    $entry = sprintf(
        "[%s] [SECURITY] Unauthorized config.php access attempt | Context: %s\n",
        gmdate('Y-m-d H:i:s'),
        json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Validate .env file permissions
 *
 * Checks if .env file has secure permissions and logs warnings if permissions
 * are too permissive. This is non-fatal to maintain compatibility with various
 * hosting environments.
 */
function validateEnvPermissions(string $envFile): void
{
    if (!file_exists($envFile)) {
        return;
    }

    $perms = fileperms($envFile);
    if ($perms === false) {
        return;
    }

    $issues = [];

    // Check if world-readable (permissions & 0004)
    if ($perms & 0004) {
        $issues[] = 'world-readable (all users can read)';
    }

    // Check if group-readable (permissions & 0040)
    if ($perms & 0040) {
        $issues[] = 'group-readable (group users can read)';
    }

    if (!empty($issues)) {
        // Log warning using inline logging (Logger not yet available)
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        $logFile = $logDir . '/app-' . gmdate('Y-m-d') . '.log';

        $permString = substr(sprintf('%o', $perms), -4);
        $context = [
            'file' => $envFile,
            'current_permissions' => $permString,
            'issues' => implode(', ', $issues),
            'recommendation' => 'Set .env permissions to 0600 or 0640 for better security',
        ];

        $entry = sprintf(
            "[%s] [WARNING] Insecure .env file permissions detected | Context: %s\n",
            gmdate('Y-m-d H:i:s'),
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}

// Execute validation before loading .env
validateConfigAccess();

$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    http_response_code(500);
    die('Missing .env file. Copy .env.example to .env and fill in your database credentials.');
}

// Validate .env file permissions (non-fatal)
validateEnvPermissions($envFile);

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

// Session timeout configuration (in seconds)
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_TIMEOUT'] ?? 1800)); // 30 min default

// SMTP configuration for password reset emails
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'MikesMarkdown');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Include logger
require_once __DIR__ . '/logger.php';

// Include mailer functions
require_once __DIR__ . '/mailer.php';
