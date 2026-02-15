# Internal Error and Security Logging

## Overview

This application includes a comprehensive internal logging system designed to capture detailed error messages and security events without exposing sensitive information to end users.

## Features

### 1. **Database Error Logging**
All database connection failures and query errors are now logged internally with detailed error information:
- Database connection errors (error code, message, SQL state)
- Failed query executions (statement errors, context)
- Operation context (user ID, affected resources)

**User Experience**: Users continue to receive generic error messages like "Database connection failed" for security, while administrators can review detailed logs.

### 2. **Security Event Logging**
The system logs critical security events including:
- **Failed login attempts** (email, reason: user not found or invalid password)
- **Successful logins** (user ID, username, email for audit trail)
- **User registrations** (new user details)
- **Rate limit violations** (IP address, action, attempt count)

### 3. **Log Levels**
The logger supports multiple severity levels:
- `DEBUG`: Detailed diagnostic information
- `INFO`: General informational messages
- `WARNING`: Warning messages for potentially harmful situations
- `ERROR`: Error events that might still allow the application to continue
- `CRITICAL`: Critical conditions that require immediate attention
- `SECURITY`: Security-related events (authentication, authorization, suspicious activity)

## Log File Structure

### Location
Logs are stored in the `/logs` directory (configurable via environment variable).

### File Naming
- Format: `app-YYYY-MM-DD.log`
- Daily rotation (one file per day)
- Automatic cleanup of logs older than 30 days (configurable)

### Log Entry Format
```
[2026-02-15 14:30:45] [SECURITY] Failed login attempt | Context: {"ip":"192.168.1.100","email":"user@example.com","reason":"invalid_password"}
```

Each log entry includes:
- **Timestamp** (UTC)
- **Severity level**
- **Message**
- **Context** (JSON-encoded details including IP, user agent, request info)

## Configuration

### Environment Variables (.env)
```env
# Enable/disable logging
LOGGING_ENABLED=true

# Custom log directory (optional)
LOG_DIR=./logs

# Log retention period in days
LOG_RETENTION_DAYS=30
```

### Default Values
- `LOGGING_ENABLED`: `true`
- `LOG_DIR`: `./logs`
- `LOG_RETENTION_DAYS`: `30`

## Security Considerations

### Sensitive Data Protection
The logger automatically redacts sensitive information:
- Passwords
- Password hashes
- API keys
- Tokens
- Authorization headers

Any context field containing these keywords will be logged as `[REDACTED]`.

### Access Control
- Log directory has restrictive permissions (750)
- Log files are excluded from version control (.gitignore)
- Logs are stored server-side only, never sent to clients

## Usage Examples

### Manual Logging
```php
$logger = getLogger();

// Log a security event
$logger->security('Suspicious activity detected', [
    'user_id' => $userId,
    'action' => 'multiple_failed_attempts',
]);

// Log a database error
$logger->databaseError('data retrieval', $db, [
    'table' => 'users',
    'operation' => 'SELECT',
]);

// Log a general error
$logger->error('Payment processing failed', [
    'order_id' => $orderId,
    'amount' => $amount,
]);
```

## Monitoring and Analysis

### Reviewing Logs
1. Access the server via SSH/SFTP
2. Navigate to the `/logs` directory
3. View log files:
   ```bash
   # View today's log
   tail -f logs/app-2026-02-15.log

   # Search for failed logins
   grep "Failed login attempt" logs/app-*.log

   # Search for database errors
   grep "Database error" logs/app-*.log

   # Count rate limit violations
   grep "Rate limit exceeded" logs/app-*.log | wc -l
   ```

### Common Patterns to Monitor

#### Attack Detection
- Multiple failed login attempts from the same IP
- Rate limit violations
- Unusual user agent strings
- Database error spikes

#### Performance Issues
- Frequent database connection failures
- Query execution errors
- Slow response times (if timing logging is added)

## Maintenance

### Automatic Cleanup
- Old log files are automatically deleted after the retention period
- Cleanup runs randomly on 1% of requests to minimize overhead
- No manual intervention required

### Manual Cleanup
```bash
# Delete logs older than 30 days
find logs/ -name "app-*.log" -mtime +30 -delete
```

## Benefits

1. **Security**: Detect and respond to attack attempts quickly
2. **Debugging**: Detailed error information helps identify root causes
3. **Compliance**: Audit trail for security events and data access
4. **Privacy**: No sensitive data exposed to end users
5. **Performance**: Minimal overhead with automatic cleanup

## Future Enhancements

Potential additions to the logging system:
- Log aggregation and centralized monitoring
- Real-time alerts for critical events
- Performance metrics logging
- Query execution time tracking
- User activity audit trails
