<?php
/**
 * Internal Error and Security Event Logger
 *
 * This logger captures detailed error messages and security events
 * for internal monitoring and debugging without exposing sensitive
 * information to end users.
 *
 * Features:
 * - Daily log rotation
 * - Multiple severity levels (DEBUG, INFO, WARNING, ERROR, CRITICAL, SECURITY)
 * - Contextual information (IP, user ID, request details)
 * - Automatic directory creation
 * - Thread-safe file writing
 */

class Logger
{
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    const SECURITY = 'SECURITY';

    private string $logDir;
    private bool $enabled;

    public function __construct(string $logDir = null, bool $enabled = true)
    {
        $this->logDir = $logDir ?? __DIR__ . '/logs';
        $this->enabled = $enabled;

        // Create log directory if it doesn't exist
        if ($this->enabled && !is_dir($this->logDir)) {
            @mkdir($this->logDir, 0750, true);
        }
    }

    /**
     * Log a message with context
     *
     * @param string $level Severity level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function log(string $level, string $message, array $context = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $date = gmdate('Y-m-d');
        $logFile = $this->logDir . '/app-' . $date . '.log';

        // Prepare context information
        $contextData = array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ], $context);

        // Sanitize sensitive data from context
        $contextData = $this->sanitizeContext($contextData);

        // Format log entry
        $logEntry = sprintf(
            "[%s] [%s] %s | Context: %s\n",
            $timestamp,
            $level,
            $message,
            json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // Thread-safe file write
        return @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Remove sensitive data from context before logging
     */
    private function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'password_hash', 'token', 'secret', 'api_key', 'authorization'];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);
            foreach ($sensitiveKeys as $sensitive) {
                if (strpos($lowerKey, $sensitive) !== false) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $context;
    }

    // Convenience methods for different log levels

    public function debug(string $message, array $context = []): bool
    {
        return $this->log(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): bool
    {
        return $this->log(self::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): bool
    {
        return $this->log(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): bool
    {
        return $this->log(self::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): bool
    {
        return $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log security-related events (failed logins, rate limits, suspicious activity)
     */
    public function security(string $message, array $context = []): bool
    {
        return $this->log(self::SECURITY, $message, $context);
    }

    /**
     * Log database errors with full error details
     */
    public function databaseError(string $operation, mysqli $db, array $context = []): bool
    {
        $context['db_error'] = $db->error;
        $context['db_errno'] = $db->errno;
        $context['db_sqlstate'] = $db->sqlstate;
        $context['operation'] = $operation;

        return $this->error("Database error during $operation", $context);
    }

    /**
     * Log prepared statement errors
     */
    public function statementError(string $operation, mysqli_stmt $stmt, array $context = []): bool
    {
        $context['stmt_error'] = $stmt->error;
        $context['stmt_errno'] = $stmt->errno;
        $context['stmt_sqlstate'] = $stmt->sqlstate;
        $context['operation'] = $operation;

        return $this->error("Statement error during $operation", $context);
    }

    /**
     * Clean up old log files (older than specified days)
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        if (!$this->enabled || !is_dir($this->logDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - ($daysToKeep * 86400);

        foreach (glob($this->logDir . '/app-*.log') as $logFile) {
            if (filemtime($logFile) < $cutoffTime) {
                if (@unlink($logFile)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}

/**
 * Get global logger instance
 */
function getLogger(): Logger
{
    static $logger = null;

    if ($logger === null) {
        $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/logs';
        $enabled = defined('LOGGING_ENABLED') ? LOGGING_ENABLED : true;
        $logger = new Logger($logDir, $enabled);
    }

    return $logger;
}
