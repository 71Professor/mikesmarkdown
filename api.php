<?php
/**
 * HedgeDoc Notes – REST API
 *
 * Endpoints:
 *   GET  ?action=list              → all notes (metadata only)
 *   GET  ?action=get&id=...        → single note with content
 *   POST { action: "create", ... } → create note
 *   POST { action: "update", ... } → update note
 *   POST { action: "delete", ... } → delete note
 *   POST { action: "togglePin", ...} → toggle pin
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Allow same-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Database connection ────────────────────────────

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    jsonError('Database connection failed', 500);
}

$db->set_charset(DB_CHARSET);

// ── Routing ────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            listNotes($db);
            break;
        case 'get':
            getNote($db, $_GET['id'] ?? '');
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
        case 'create':
            createNote($db, $body);
            break;
        case 'update':
            updateNote($db, $body);
            break;
        case 'delete':
            deleteNote($db, $body);
            break;
        case 'togglePin':
            togglePin($db, $body);
            break;
        default:
            jsonError('Unknown action', 400);
    }
} else {
    jsonError('Method not allowed', 405);
}

$db->close();

// ── Handlers ───────────────────────────────────────

function listNotes(mysqli $db): void
{
    $search = trim($_GET['q'] ?? '');

    if ($search !== '') {
        $stmt = $db->prepare(
            "SELECT id, title, content, pinned, created, lastAccessed, updated
             FROM notes
             WHERE title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%')
             ORDER BY pinned DESC, lastAccessed DESC"
        );
        $stmt->bind_param('ss', $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query(
            "SELECT id, title, content, pinned, created, lastAccessed, updated
             FROM notes ORDER BY pinned DESC, lastAccessed DESC"
        );
    }

    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $row['pinned'] = (bool) $row['pinned'];
        $row['preview'] = makePreview($row['content']);
        unset($row['content']);
        $notes[] = $row;
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    jsonSuccess($notes);
}

function getNote(mysqli $db, string $id): void
{
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, content, pinned, created, lastAccessed, updated FROM notes WHERE id = ?"
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();

    if (!$note) {
        jsonError('Note not found', 404);
    }

    $note['pinned'] = (bool) $note['pinned'];

    // Update lastAccessed
    $now = gmdate('Y-m-d H:i:s');
    $upd = $db->prepare("UPDATE notes SET lastAccessed = ? WHERE id = ?");
    $upd->bind_param('ss', $now, $id);
    $upd->execute();
    $upd->close();

    $note['lastAccessed'] = $now;

    jsonSuccess($note);
}

function createNote(mysqli $db, array $body): void
{
    $content = $body['content'] ?? "# Neue Notiz\n\nSchreibe hier deinen Text...\n";
    $title = $body['title'] ?? extractTitle($content);
    $id = generateId();
    $now = gmdate('Y-m-d H:i:s');
    $pinned = 0;

    $stmt = $db->prepare(
        "INSERT INTO notes (id, title, content, pinned, created, lastAccessed) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssiss', $id, $title, $content, $pinned, $now, $now);

    if (!$stmt->execute()) {
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

function updateNote(mysqli $db, array $body): void
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
        $stmt = $db->prepare("UPDATE notes SET title = ?, content = ?, lastAccessed = ? WHERE id = ?");
        $stmt->bind_param('ssss', $title, $content, $now, $id);
    } elseif ($title !== null) {
        $stmt = $db->prepare("UPDATE notes SET title = ?, lastAccessed = ? WHERE id = ?");
        $stmt->bind_param('sss', $title, $now, $id);
    } else {
        $stmt = $db->prepare("UPDATE notes SET lastAccessed = ? WHERE id = ?");
        $stmt->bind_param('ss', $now, $id);
    }

    if (!$stmt->execute()) {
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

function deleteNote(mysqli $db, array $body): void
{
    $id = $body['id'] ?? '';
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $stmt = $db->prepare("DELETE FROM notes WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->close();

    jsonSuccess(['deleted' => true]);
}

function togglePin(mysqli $db, array $body): void
{
    $id = $body['id'] ?? '';
    if (!$id) {
        jsonError('Missing id', 400);
    }

    $stmt = $db->prepare("UPDATE notes SET pinned = NOT pinned WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->close();

    // Return new state
    $stmt = $db->prepare("SELECT pinned FROM notes WHERE id = ?");
    $stmt->bind_param('s', $id);
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

function makePreview(string $markdown): string
{
    // Strip markdown syntax for a plain-text excerpt
    $text = preg_replace('/^#{1,6}\s+/m', '', $markdown);
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
    // First H1 heading
    if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
        return mb_substr(trim($m[1]), 0, 255);
    }
    // Fallback: first non-empty line
    foreach (explode("\n", $markdown) as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            return mb_substr($trimmed, 0, 60);
        }
    }
    return 'Unbenannte Notiz';
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
