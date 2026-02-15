<?php
/**
 * HedgeDoc Notes – REST API
 *
 * Auth Endpoints:
 *   POST { action: "register", username, email, password }
 *   POST { action: "login", email, password }
 *   POST { action: "logout" }
 *   GET  ?action=session                → current user info
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
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

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

    // Auto-login after registration
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;

    // Log successful registration for audit trail
    $logger = getLogger();
    $logger->security('New user registered', [
        'user_id' => $userId,
        'username' => $username,
        'email' => $email,
    ]);

    jsonSuccess([
        'id' => $userId,
        'username' => $username,
        'email' => $email,
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

    $stmt = $db->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
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

    // Regenerate session to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

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
