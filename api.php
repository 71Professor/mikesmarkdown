<?php
/**
 * HedgeDoc Notes – REST API
 *
 * Auth Endpoints:
 *   POST { action: "register", username, email, password }
 *   POST { action: "login", email, password }
 *   POST { action: "logout" }
 *   GET  ?action=session                → current user info
 *   POST { action: "requestPasswordReset", email } → request password reset
 *   POST { action: "resetPassword", token, newPassword } → reset password with token
 *   POST { action: "changePassword", currentPassword, newPassword } → change password (requires auth)
 *
 * Note Endpoints (require authentication):
 *   GET  ?action=list              → all notes for current user
 *   GET  ?action=get&id=...        → single note with content
 *   POST { action: "create", ... } → create note
 *   POST { action: "update", ... } → update note
 *   POST { action: "delete", ... } → delete note
 *   POST { action: "togglePin", ...} → toggle pin
 */

require_once __DIR__ . '/config.php';

// ── Session (hardened cookie settings) ────────────

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');  // Lax allows cookies on top-level navigations (email links)
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

// Session timeout configuration
$sessionTimeout = SESSION_TIMEOUT;
ini_set('session.gc_maxlifetime', (string)$sessionTimeout);
ini_set('session.cookie_lifetime', '0');  // Session cookie (browser close)
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');     // 1% cleanup probability

session_start();

// Check session activity for authenticated endpoints only
// Skip for auth-related endpoints to avoid interfering with authentication flow
$action = $_GET['action'] ?? ($_POST['action'] ?? json_decode(file_get_contents('php://input'), true)['action'] ?? '');
$authEndpoints = ['login', 'register', 'session', 'verifyEmail', 'resetPassword', 'requestPasswordReset', 'resendVerificationEmail'];
if (!in_array($action, $authEndpoints, true)) {
    checkSessionActivity();
}

// ── Security headers ──────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS: only allow same-origin (no cross-origin requests needed for this SPA)
// If cross-origin is required, replace '*' with the specific allowed origin.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Rate limiting (IP-based, database-backed) ─────────

/**
 * IP-based rate limiting using database storage
 * This prevents bypass attempts via session/cookie manipulation
 *
 * @param mysqli $db Database connection
 * @param string $action Action being rate-limited (e.g., 'login', 'register')
 * @param int $maxAttempts Maximum allowed attempts within the time window
 * @param int $windowSeconds Time window in seconds
 * @throws void Terminates with 429 error if rate limit exceeded
 */
function checkRateLimit(mysqli $db, string $action, int $maxAttempts = 5, int $windowSeconds = 300): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Validate IP address to prevent injection
    if ($ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = 'invalid';
    }

    $now = gmdate('Y-m-d H:i:s');

    // Check for existing rate limit record
    $stmt = $db->prepare("SELECT id, attempt_count, first_attempt, last_attempt FROM rate_limits WHERE ip_address = ? AND action = ?");
    $stmt->bind_param('ss', $ip, $action);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if ($record) {
        $firstAttemptTime = strtotime($record['first_attempt']);
        $currentTime = time();

        // Check if the time window has expired
        if ($currentTime - $firstAttemptTime > $windowSeconds) {
            // Window expired, reset counter
            $stmt = $db->prepare("UPDATE rate_limits SET attempt_count = 1, first_attempt = ?, last_attempt = ? WHERE id = ?");
            $stmt->bind_param('ssi', $now, $now, $record['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            // Within window, increment counter
            $newCount = $record['attempt_count'] + 1;

            // Check if limit exceeded
            if ($newCount > $maxAttempts) {
                $retryAfter = $firstAttemptTime + $windowSeconds - $currentTime;
                // Log rate limit violation
                $logger = getLogger();
                $logger->security('Rate limit exceeded', [
                    'action' => $action,
                    'ip' => $ip,
                    'attempts' => $newCount,
                    'max_attempts' => $maxAttempts,
                    'retry_after' => $retryAfter,
                ]);
                header('Retry-After: ' . $retryAfter);
                jsonError('Zu viele Versuche. Bitte warten Sie ' . ceil($retryAfter / 60) . ' Minuten.', 429);
            }

            // Update attempt count
            $stmt = $db->prepare("UPDATE rate_limits SET attempt_count = ?, last_attempt = ? WHERE id = ?");
            $stmt->bind_param('isi', $newCount, $now, $record['id']);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // First attempt, create new record
        $stmt = $db->prepare("INSERT INTO rate_limits (ip_address, action, attempt_count, first_attempt, last_attempt) VALUES (?, ?, 1, ?, ?)");
        $stmt->bind_param('ssss', $ip, $action, $now, $now);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Cleanup old rate limit entries (older than 1 day)
 * Called periodically to prevent database bloat
 *
 * @param mysqli $db Database connection
 */
function cleanupRateLimits(mysqli $db): void
{
    // Only run cleanup 1% of the time to reduce overhead
    if (rand(1, 100) > 1) {
        return;
    }

    $db->query("DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 1 DAY)");
}

// ── Database connection ────────────────────────────

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    // Log detailed error internally
    $logger = getLogger();
    $logger->critical('Database connection failed', [
        'error' => $db->connect_error,
        'errno' => $db->connect_errno,
        'host' => DB_HOST,
        'database' => DB_NAME,
    ]);
    // Return generic error to user
    jsonError('Database connection failed', 500);
}

$db->set_charset(DB_CHARSET);

// Periodic cleanup of old rate limit entries
cleanupRateLimits($db);

// Periodic cleanup of old log files (1% chance per request)
if (rand(1, 100) === 1) {
    $logger = getLogger();
    $logger->cleanupOldLogs(LOG_RETENTION_DAYS);
}

// ── Helper: get current user ID from session ──────

function getCurrentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function requireAuth(): int
{
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonError('Nicht angemeldet', 401);
    }
    return $userId;
}

/**
 * Check session activity and invalidate if timeout exceeded
 * Tracks last activity timestamp to enforce inactivity timeout
 *
 * @return void Terminates with 401 if session expired
 */
function checkSessionActivity(): void
{
    $timeout = SESSION_TIMEOUT;

    // Check if last activity timestamp exists
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];

        if ($inactive > $timeout) {
            // Session expired due to inactivity
            $logger = getLogger();
            $logger->security('Session expired due to inactivity', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'inactive_seconds' => $inactive,
                'timeout_seconds' => $timeout,
            ]);

            // Clear session data
            session_unset();
            session_destroy();

            // Return 401 to trigger frontend redirect
            jsonError('Session abgelaufen wegen Inaktivität', 401);
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Validates password complexity requirements
 * Returns null if valid, or an error message string if invalid
 */
function validatePasswordComplexity(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Passwort muss mindestens 8 Zeichen lang sein';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Passwort muss mindestens einen Großbuchstaben enthalten';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Passwort muss mindestens einen Kleinbuchstaben enthalten';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Passwort muss mindestens eine Ziffer enthalten';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Passwort muss mindestens ein Sonderzeichen enthalten';
    }

    return null;
}

// ── Routing ────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'session':
            getSession();
            break;
        case 'list':
            $userId = requireAuth();
            listNotes($db, $userId);
            break;
        case 'get':
            $userId = requireAuth();
            getNote($db, $userId, $_GET['id'] ?? '');
            break;
        default:
            jsonError('Unknown action', 400);
    }
} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['action'])) {
        jsonError('Invalid request body', 400);
    }

    switch ($body['action']) {
        // Auth actions (no auth required)
        case 'register':
            registerUser($db, $body);
            break;
        case 'login':
            loginUser($db, $body);
            break;
        case 'logout':
            logoutUser();
            break;
        case 'requestPasswordReset':
            requestPasswordReset($db, $body);
            break;
        case 'resetPassword':
            resetPassword($db, $body);
            break;
        case 'verifyEmail':
            verifyEmail($db, $body);
            break;
        case 'resendVerificationEmail':
            resendVerificationEmail($db, $body);
            break;

        // Auth actions (auth required)
        case 'changePassword':
            $userId = requireAuth();
            changePassword($db, $userId, $body);
            break;

        // Note actions (auth required)
        case 'create':
            $userId = requireAuth();
            createNote($db, $userId, $body);
            break;
        case 'update':
            $userId = requireAuth();
            updateNote($db, $userId, $body);
            break;
        case 'delete':
            $userId = requireAuth();
            deleteNote($db, $userId, $body);
            break;
        case 'togglePin':
            $userId = requireAuth();
            togglePin($db, $userId, $body);
            break;
        default:
            jsonError('Unknown action', 400);
    }
} else {
    jsonError('Method not allowed', 405);
}

$db->close();

// ══════════════════════════════════════════════════
//  Auth Handlers
// ══════════════════════════════════════════════════

function registerUser(mysqli $db, array $body): void
{
    checkRateLimit($db, 'register', 5, 600); // 5 attempts per 10 minutes

    $username = trim($body['username'] ?? '');
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        jsonError('Alle Felder sind erforderlich', 400);
    }

    if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        jsonError('Benutzername muss zwischen 3 und 50 Zeichen lang sein', 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Ungültige E-Mail-Adresse', 400);
    }

    // Validate password complexity
    $passwordError = validatePasswordComplexity($password);
    if ($passwordError !== null) {
        jsonError($passwordError, 400);
    }

    // Check if username or email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        jsonError('Benutzername oder E-Mail bereits vergeben', 409);
    }
    $stmt->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $email, $passwordHash);

    if (!$stmt->execute()) {
        // Log detailed error internally
        $logger = getLogger();
        $logger->databaseError('user registration', $db, [
            'username' => $username,
            'email' => $email,
        ]);
        $stmt->close();
        jsonError('Registrierung fehlgeschlagen', 500);
    }

    $userId = (int) $stmt->insert_id;
    $stmt->close();

    // Generate email verification token
    $token = bin2hex(random_bytes(32));
    $now = gmdate('Y-m-d H:i:s');
    $expires = gmdate('Y-m-d H:i:s', time() + 86400); // 24 hours

    // Insert verification token
    $stmt = $db->prepare("INSERT INTO email_verification_tokens (user_id, token, created, expires) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $token, $now, $expires);

    if (!$stmt->execute()) {
        $logger = getLogger();
        $logger->databaseError('email verification token creation', $db, ['user_id' => $userId]);
        $stmt->close();
        jsonError('Registrierung fehlgeschlagen', 500);
    }
    $stmt->close();

    // Send verification email with error handling
    try {
        sendVerificationEmail($email, $token);
        $logger = getLogger();
        $logger->info('Verification email sent', ['user_id' => $userId, 'email' => $email]);
    } catch (Exception $e) {
        $logger = getLogger();
        $logger->error('Failed to send verification email', [
            'user_id' => $userId,
            'email' => $email,
            'exception' => $e->getMessage()
        ]);
        // IMPORTANT: Still return success to prevent email enumeration
    }

    // Log successful registration (pending verification) for audit trail
    $logger = getLogger();
    $logger->security('New user registered - pending verification', [
        'user_id' => $userId,
        'username' => $username,
        'email' => $email,
    ]);

    // Modified response - no auto-login, user must verify email first
    jsonSuccess([
        'message' => 'Registrierung erfolgreich! Bitte prüfen Sie Ihre E-Mails zur Bestätigung.',
        'email' => $email
    ]);
}

function loginUser(mysqli $db, array $body): void
{
    checkRateLimit($db, 'login', 5, 300); // 5 attempts per 5 minutes

    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if ($email === '' || $password === '') {
        jsonError('E-Mail und Passwort sind erforderlich', 400);
    }

    $stmt = $db->prepare("SELECT id, username, email, password_hash, is_email_verified FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Log failed login attempt
        $logger = getLogger();
        $logger->security('Failed login attempt', [
            'email' => $email,
            'reason' => !$user ? 'user_not_found' : 'invalid_password',
        ]);
        jsonError('Ungültige Anmeldedaten', 401);
    }

    // Check if email is verified
    if (!$user['is_email_verified']) {
        $logger = getLogger();
        $logger->security('Login attempt with unverified email', [
            'user_id' => (int) $user['id'],
            'email' => $email,
        ]);
        jsonError('E-Mail-Adresse noch nicht bestätigt. Bitte prüfen Sie Ihre E-Mails.', 403);
    }

    // Regenerate session to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_activity'] = time();  // Initialize activity tracking

    // Log successful login for audit trail
    $logger = getLogger();
    $logger->security('Successful login', [
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $email,
    ]);

    jsonSuccess([
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
    ]);
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    jsonSuccess(['loggedOut' => true]);
}

function getSession(): void
{
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonSuccess(null);
        return;
    }

    jsonSuccess([
        'id' => $userId,
        'username' => $_SESSION['username'] ?? '',
    ]);
}

function requestPasswordReset(mysqli $db, array $body): void
{
    checkRateLimit($db, 'password_reset', 3, 600); // 3 attempts per 10 minutes

    $email = trim($body['email'] ?? '');

    if ($email === '') {
        jsonError('E-Mail ist erforderlich', 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Ungültige E-Mail-Adresse', 400);
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Always return success even if user doesn't exist (security best practice)
    // This prevents email enumeration attacks
    if (!$user) {
        $logger = getLogger();
        $logger->security('Password reset requested for non-existent email', [
            'email' => $email,
        ]);
        jsonSuccess(['message' => 'Falls die E-Mail-Adresse existiert, wurde ein Reset-Link gesendet']);
        return;
    }

    $userId = (int) $user['id'];

    // Generate secure random token
    $token = bin2hex(random_bytes(32)); // 64 character hex string
    $now = gmdate('Y-m-d H:i:s');
    $expires = gmdate('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

    // Delete any existing tokens for this user
    $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    // Insert new token
    $stmt = $db->prepare("INSERT INTO password_reset_tokens (user_id, token, created, expires) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $token, $now, $expires);

    if (!$stmt->execute()) {
        $logger = getLogger();
        $logger->databaseError('password reset token creation', $db, [
            'user_id' => $userId,
            'email' => $email,
        ]);
        $stmt->close();
        jsonError('Fehler beim Erstellen des Reset-Tokens', 500);
    }
    $stmt->close();

    // Log password reset request
    $logger = getLogger();
    $logger->security('Password reset requested', [
        'user_id' => $userId,
        'email' => $email,
        'token_expires' => $expires,
    ]);

    // Send password reset email with error handling
    try {
        sendPasswordResetEmail($email, $token);

        $logger->info('Password reset email sent successfully', [
            'user_id' => $userId,
            'email' => $email,
        ]);
    } catch (Exception $e) {
        $logger->error('Exception while sending password reset email', [
            'user_id' => $userId,
            'email' => $email,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        // Show detailed error during development to help with troubleshooting
        // In production, you may want to return a generic success message to prevent email enumeration
        jsonError($e->getMessage(), 500);
    }

    // Always return success message (prevents email enumeration)
    jsonSuccess([
        'message' => 'Falls die E-Mail-Adresse existiert, wurde ein Reset-Link gesendet',
    ]);
}

function resetPassword(mysqli $db, array $body): void
{
    checkRateLimit($db, 'password_reset_verify', 5, 300); // 5 attempts per 5 minutes

    $token = trim($body['token'] ?? '');
    $newPassword = $body['newPassword'] ?? '';

    if ($token === '' || $newPassword === '') {
        jsonError('Token und neues Passwort sind erforderlich', 400);
    }

    // Validate password complexity
    $passwordError = validatePasswordComplexity($newPassword);
    if ($passwordError !== null) {
        jsonError($passwordError, 400);
    }

    // Find valid, unused token
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT id, user_id FROM password_reset_tokens
         WHERE token = ? AND expires > ? AND used = 0"
    );
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenRecord = $result->fetch_assoc();
    $stmt->close();

    if (!$tokenRecord) {
        $logger = getLogger();
        $logger->security('Invalid or expired password reset token used', [
            'token_prefix' => substr($token, 0, 8) . '...',
        ]);
        jsonError('Ungültiger oder abgelaufener Reset-Link', 400);
    }

    $userId = (int) $tokenRecord['user_id'];
    $tokenId = (int) $tokenRecord['id'];

    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update user password
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $passwordHash, $userId);

    if (!$stmt->execute()) {
        $logger = getLogger();
        $logger->databaseError('password reset', $db, [
            'user_id' => $userId,
        ]);
        $stmt->close();
        jsonError('Fehler beim Zurücksetzen des Passworts', 500);
    }
    $stmt->close();

    // Mark token as used
    $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
    $stmt->bind_param('i', $tokenId);
    $stmt->execute();
    $stmt->close();

    // Log successful password reset
    $logger = getLogger();
    $logger->security('Password successfully reset', [
        'user_id' => $userId,
    ]);

    jsonSuccess(['message' => 'Passwort erfolgreich zurückgesetzt']);
}

function verifyEmail(mysqli $db, array $body): void
{
    checkRateLimit($db, 'email_verification', 10, 600); // 10 attempts per 10 minutes

    $token = trim($body['token'] ?? '');

    if ($token === '') {
        jsonError('Verifizierungstoken ist erforderlich', 400);
    }

    // Find valid, unused token
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT id, user_id FROM email_verification_tokens
         WHERE token = ? AND expires > ? AND used = 0"
    );
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenRecord = $result->fetch_assoc();
    $stmt->close();

    if (!$tokenRecord) {
        // Token could be already used - check if user is already verified
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.is_email_verified
            FROM email_verification_tokens evt
            INNER JOIN users u ON u.id = evt.user_id
            WHERE evt.token = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && $user['is_email_verified']) {
            // User is already verified - return success message
            $logger = getLogger();
            $logger->security('Email already verified', [
                'user_id' => $user['id'],
                'email' => $user['email'],
            ]);

            jsonSuccess([
                'message' => 'E-Mail bereits bestätigt. Bitte loggen Sie sich ein.'
            ]);
        }

        // Invalid or expired token
        $logger = getLogger();
        $logger->security('Invalid or expired email verification token used', [
            'token_prefix' => substr($token, 0, 8) . '...',
        ]);
        jsonError('Ungültiger oder abgelaufener Verifizierungslink', 400);
    }

    $userId = (int) $tokenRecord['user_id'];
    $tokenId = (int) $tokenRecord['id'];

    // Get user details for logging
    $stmt = $db->prepare("SELECT email, is_email_verified FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        jsonError('Benutzer nicht gefunden', 404);
    }

    // Check if already verified
    if ($user['is_email_verified']) {
        // Already verified
        $logger = getLogger();
        $logger->info('Email already verified', ['user_id' => $userId]);
    } else {
        // Mark email as verified
        $verifiedAt = gmdate('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE users SET is_email_verified = 1, email_verified_at = ? WHERE id = ?");
        $stmt->bind_param('si', $verifiedAt, $userId);

        if (!$stmt->execute()) {
            $logger = getLogger();
            $logger->databaseError('email verification', $db, ['user_id' => $userId]);
            $stmt->close();
            jsonError('Fehler bei der E-Mail-Verifizierung', 500);
        }
        $stmt->close();
    }

    // Mark token as used
    $stmt = $db->prepare("UPDATE email_verification_tokens SET used = 1 WHERE id = ?");
    $stmt->bind_param('i', $tokenId);
    $stmt->execute();
    $stmt->close();

    // Log successful verification
    $logger = getLogger();
    $logger->security('Email verified successfully', [
        'user_id' => $userId,
        'email' => $user['email'],
    ]);

    jsonSuccess([
        'message' => 'Danke. Ihre E-Mail ist bestätigt. Loggen Sie sich jetzt auf der Login-Seite ein.'
    ]);
}

function resendVerificationEmail(mysqli $db, array $body): void
{
    checkRateLimit($db, 'resend_verification', 3, 600); // 3 attempts per 10 minutes

    $email = trim($body['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Gültige E-Mail-Adresse ist erforderlich', 400);
    }

    // Find user by email
    $stmt = $db->prepare("SELECT id, username, is_email_verified FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Always return success to prevent email enumeration
    if (!$user) {
        $logger = getLogger();
        $logger->info('Verification resend requested for non-existent email', ['email' => $email]);
        jsonSuccess(['message' => 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde eine Verifizierungs-E-Mail gesendet.']);
        return;
    }

    $userId = (int) $user['id'];

    // Check if already verified
    if ($user['is_email_verified']) {
        jsonError('E-Mail-Adresse bereits verifiziert', 400);
    }

    // Delete existing tokens for this user
    $stmt = $db->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    // Generate new token
    $token = bin2hex(random_bytes(32));
    $now = gmdate('Y-m-d H:i:s');
    $expires = gmdate('Y-m-d H:i:s', time() + 86400); // 24 hours

    // Insert new token
    $stmt = $db->prepare("INSERT INTO email_verification_tokens (user_id, token, created, expires) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $token, $now, $expires);

    if (!$stmt->execute()) {
        $logger = getLogger();
        $logger->databaseError('verification token recreation', $db, ['user_id' => $userId]);
        $stmt->close();
        jsonError('Fehler beim Erstellen des Verifizierungstokens', 500);
    }
    $stmt->close();

    // Send verification email
    try {
        sendVerificationEmail($email, $token);
        $logger = getLogger();
        $logger->info('Verification email resent', ['user_id' => $userId, 'email' => $email]);
    } catch (Exception $e) {
        $logger = getLogger();
        $logger->error('Failed to resend verification email', [
            'user_id' => $userId,
            'email' => $email,
            'exception' => $e->getMessage()
        ]);
        // Still return success to prevent email enumeration
    }

    jsonSuccess(['message' => 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde eine Verifizierungs-E-Mail gesendet.']);
}

function changePassword(mysqli $db, int $userId, array $body): void
{
    checkRateLimit($db, 'password_change', 5, 300); // 5 attempts per 5 minutes

    $currentPassword = $body['currentPassword'] ?? '';
    $newPassword = $body['newPassword'] ?? '';

    if ($currentPassword === '' || $newPassword === '') {
        jsonError('Aktuelles und neues Passwort sind erforderlich', 400);
    }

    // Validate new password complexity
    $passwordError = validatePasswordComplexity($newPassword);
    if ($passwordError !== null) {
        jsonError($passwordError, 400);
    }

    // Get current password hash
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        jsonError('Benutzer nicht gefunden', 404);
    }

    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        $logger = getLogger();
        $logger->security('Failed password change attempt - incorrect current password', [
            'user_id' => $userId,
        ]);
        jsonError('Aktuelles Passwort ist falsch', 401);
    }

    // Check if new password is same as current
    if ($currentPassword === $newPassword) {
        jsonError('Neues Passwort muss sich vom aktuellen unterscheiden', 400);
    }

    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $passwordHash, $userId);

    if (!$stmt->execute()) {
        $logger = getLogger();
        $logger->databaseError('password change', $db, [
            'user_id' => $userId,
        ]);
        $stmt->close();
        jsonError('Fehler beim Ändern des Passworts', 500);
    }
    $stmt->close();

    // Log successful password change
    $logger = getLogger();
    $logger->security('Password successfully changed', [
        'user_id' => $userId,
    ]);

    jsonSuccess(['message' => 'Passwort erfolgreich geändert']);
}

// ══════════════════════════════════════════════════
//  Note Handlers
// ══════════════════════════════════════════════════

function listNotes(mysqli $db, int $userId): void
{
    $search = trim($_GET['q'] ?? '');

    if ($search !== '') {
        $stmt = $db->prepare(
            "SELECT id, title, content, pinned, created, lastAccessed, updated
             FROM notes
             WHERE user_id = ? AND (title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%'))
             ORDER BY pinned DESC, lastAccessed DESC"
        );
        $stmt->bind_param('iss', $userId, $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $stmt = $db->prepare(
            "SELECT id, title, content, pinned, created, lastAccessed, updated
             FROM notes WHERE user_id = ? ORDER BY pinned DESC, lastAccessed DESC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $row['pinned'] = (bool) $row['pinned'];
        $row['preview'] = makePreview($row['content']);
        $row['tags'] = extractTags($row['content']);
        unset($row['content']);
        $notes[] = $row;
    }

    $stmt->close();

    jsonSuccess($notes);
}

function getNote(mysqli $db, int $userId, string $id): void
{
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, content, pinned, created, lastAccessed, updated FROM notes WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param('si', $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();

    if (!$note) {
        jsonError('Note not found', 404);
    }

    $note['pinned'] = (bool) $note['pinned'];
    $note['tags'] = extractTags($note['content']);

    // Update lastAccessed
    $now = gmdate('Y-m-d H:i:s');
    $upd = $db->prepare("UPDATE notes SET lastAccessed = ? WHERE id = ? AND user_id = ?");
    $upd->bind_param('ssi', $now, $id, $userId);
    $upd->execute();
    $upd->close();

    $note['lastAccessed'] = $now;

    jsonSuccess($note);
}

function createNote(mysqli $db, int $userId, array $body): void
{
    $content = $body['content'] ?? "# Neue Notiz\n\nSchreibe hier deinen Text...\n";
    $title = $body['title'] ?? extractTitle($content);
    $id = generateId();
    $now = gmdate('Y-m-d H:i:s');
    $pinned = 0;

    $stmt = $db->prepare(
        "INSERT INTO notes (id, user_id, title, content, pinned, created, lastAccessed) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sississ', $id, $userId, $title, $content, $pinned, $now, $now);

    if (!$stmt->execute()) {
        // Log detailed error internally
        $logger = getLogger();
        $logger->databaseError('note creation', $db, [
            'user_id' => $userId,
            'note_id' => $id,
        ]);
        $stmt->close();
        jsonError('Failed to create note', 500);
    }
    $stmt->close();

    jsonSuccess([
        'id' => $id,
        'title' => $title,
        'content' => $content,
        'pinned' => false,
        'created' => $now,
        'lastAccessed' => $now,
    ]);
}

function updateNote(mysqli $db, int $userId, array $body): void
{
    $id = $body['id'] ?? '';
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $content = $body['content'] ?? null;
    $title = $body['title'] ?? null;

    // Auto-extract title from content if content changed but no explicit title
    if ($content !== null && $title === null) {
        $title = extractTitle($content);
    }

    $now = gmdate('Y-m-d H:i:s');

    if ($content !== null && $title !== null) {
        $stmt = $db->prepare("UPDATE notes SET title = ?, content = ?, lastAccessed = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ssssi', $title, $content, $now, $id, $userId);
    } elseif ($title !== null) {
        $stmt = $db->prepare("UPDATE notes SET title = ?, lastAccessed = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sssi', $title, $now, $id, $userId);
    } else {
        $stmt = $db->prepare("UPDATE notes SET lastAccessed = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ssi', $now, $id, $userId);
    }

    if (!$stmt->execute()) {
        // Log detailed error internally
        $logger = getLogger();
        $logger->databaseError('note update', $db, [
            'user_id' => $userId,
            'note_id' => $id,
        ]);
        $stmt->close();
        jsonError('Failed to update note', 500);
    }

    if ($stmt->affected_rows === 0 && $db->errno) {
        $stmt->close();
        jsonError('Note not found', 404);
    }
    $stmt->close();

    jsonSuccess(['id' => $id, 'title' => $title, 'lastAccessed' => $now]);
}

function deleteNote(mysqli $db, int $userId, array $body): void
{
    $id = $body['id'] ?? '';
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $stmt = $db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param('si', $id, $userId);
    $stmt->execute();
    $stmt->close();

    jsonSuccess(['deleted' => true]);
}

function togglePin(mysqli $db, int $userId, array $body): void
{
    $id = $body['id'] ?? '';
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $stmt = $db->prepare("UPDATE notes SET pinned = NOT pinned WHERE id = ? AND user_id = ?");
    $stmt->bind_param('si', $id, $userId);
    $stmt->execute();
    $stmt->close();

    // Return new state
    $stmt = $db->prepare("SELECT pinned FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param('si', $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        jsonError('Note not found', 404);
    }

    jsonSuccess(['id' => $id, 'pinned' => (bool) $row['pinned']]);
}

// ── Helpers ────────────────────────────────────────

function stripFrontMatter(string $markdown): string
{
    // Remove YAML front matter block (--- ... ---)
    return preg_replace('/\A---\s*\n.*?\n---\s*\n?/s', '', $markdown);
}

function makePreview(string $markdown): string
{
    // Strip front matter first
    $text = stripFrontMatter($markdown);
    // Strip markdown syntax for a plain-text excerpt
    $text = preg_replace('/^#{1,6}\s+/m', '', $text);
    $text = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $text);
    $text = preg_replace('/~~([^~]+)~~/', '$1', $text);
    $text = preg_replace('/`([^`]+)`/', '$1', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
    $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '', $text);
    $text = preg_replace('/^[-*+]\s+/m', '', $text);
    $text = preg_replace('/^\d+\.\s+/m', '', $text);
    $text = preg_replace('/^>\s+/m', '', $text);
    $text = str_replace('---', '', $text);
    $text = preg_replace('/\n{2,}/', "\n", $text);
    return mb_substr(trim($text), 0, 150);
}

function generateId(): string
{
    return base_convert((string) time(), 10, 36) . bin2hex(random_bytes(4));
}

function extractTitle(string $markdown): string
{
    // Strip front matter before looking for title
    $body = stripFrontMatter($markdown);
    // First H1 heading
    if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
        return mb_substr(trim($m[1]), 0, 255);
    }
    // Fallback: first non-empty line
    foreach (explode("\n", $body) as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            return mb_substr($trimmed, 0, 60);
        }
    }
    return 'Unbenannte Notiz';
}

function extractTags(string $markdown): array
{
    // Match YAML front matter block
    if (!preg_match('/\A---\s*\n(.*?)\n---/s', $markdown, $fm)) {
        return [];
    }
    $frontMatter = $fm[1];

    // Try inline format: tags: tag1, tag2, tag3
    if (preg_match('/^tags:\s*(.+)$/m', $frontMatter, $m)) {
        $value = trim($m[1]);
        // Check if it's not a YAML list start (just a dash)
        if ($value !== '' && $value[0] !== '-') {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
    }

    // Try YAML list format:
    // tags:
    //   - tag1
    //   - tag2
    if (preg_match('/^tags:\s*\n((?:\s+-\s+.+\n?)+)/m', $frontMatter, $m)) {
        $lines = explode("\n", trim($m[1]));
        $tags = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s+-\s+(.+)$/', $line, $t)) {
                $tag = trim($t[1]);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }
        return $tags;
    }

    return [];
}

function jsonSuccess($data): void
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
