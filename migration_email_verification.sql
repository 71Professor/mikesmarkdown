-- E-Mail-Verifizierungs-Token-Tabelle erstellen
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created DATETIME NOT NULL,
    expires DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_token (token),
    INDEX idx_expires (expires),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-Mail-Verifizierungs-Spalten zur users-Tabelle hinzufügen
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash,
ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER is_email_verified;

-- Index hinzufügen (falls nicht vorhanden)
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_email_verified (is_email_verified);

-- Bestehende Benutzer als verifiziert markieren
UPDATE users SET is_email_verified = 1 WHERE id > 0;
