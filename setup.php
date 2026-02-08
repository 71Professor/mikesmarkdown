<?php
/**
 * HedgeDoc Notes – Database Setup
 *
 * Run this once to create the notes table.
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

$sql = "CREATE TABLE IF NOT EXISTS notes (
    id VARCHAR(16) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastAccessed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pinned (pinned),
    INDEX idx_lastAccessed (lastAccessed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sql)) {
    echo '<p style="color:green;">Table <code>notes</code> created successfully.</p>';
    echo '<p><strong>Delete this file now!</strong> Then open <a href="index.html">your app</a>.</p>';
} else {
    echo '<p style="color:red;">Error: ' . htmlspecialchars($mysqli->error) . '</p>';
}

$mysqli->close();
