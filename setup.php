<?php
/**
 * HedgeDoc Notes – Database Setup
 *
 * Run this once to create the notes and users tables.
 * Access: https://yourdomain.com/setup.php
 * Delete this file after setup is complete.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h2>HedgeDoc Notes – Database Setup</h2>';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    echo '<p style="color:red;">Connection failed: ' . htmlspecialchars($mysqli->connect_error) . '</p>';
    echo '<p>Check your credentials in <code>config.php</code>.</p>';
    exit;
}

$mysqli->set_charset(DB_CHARSET);

// Create users table
$sqlUsers = "CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sqlUsers)) {
    echo '<p style="color:green;">Table <code>users</code> created successfully.</p>';
} else {
    echo '<p style="color:red;">Error creating users table: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// Create notes table with user_id
$sqlNotes = "CREATE TABLE IF NOT EXISTS notes (
    id VARCHAR(16) NOT NULL PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastAccessed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_pinned (pinned),
    INDEX idx_lastAccessed (lastAccessed),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sqlNotes)) {
    echo '<p style="color:green;">Table <code>notes</code> created successfully.</p>';
} else {
    echo '<p style="color:red;">Error creating notes table: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// Migration: add user_id column if notes table already exists without it
$result = $mysqli->query("SHOW COLUMNS FROM notes LIKE 'user_id'");
if ($result && $result->num_rows === 0) {
    $mysqli->query("ALTER TABLE notes ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id");
    $mysqli->query("ALTER TABLE notes ADD INDEX idx_user_id (user_id)");
    echo '<p style="color:orange;">Migration: added <code>user_id</code> column to existing notes table.</p>';
}

echo '<p><strong>Delete this file now!</strong> Then open <a href="index.html">your app</a>.</p>';

$mysqli->close();
