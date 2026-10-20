<?php
declare(strict_types=1);
return [
    "up" => function (PDO $db): void {
        $db->exec(
            "ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER NOT NULL DEFAULT 0",
        );
        $db->exec(
            "CREATE TABLE two_factor_challenges (id VARCHAR(64) PRIMARY KEY, user_id INTEGER NOT NULL, purpose VARCHAR(16) NOT NULL, session_version INTEGER NOT NULL, code_hash VARCHAR(255) NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, expires_at BIGINT NOT NULL)",
        );
        $db->exec(
            "CREATE UNIQUE INDEX two_factor_user_purpose ON two_factor_challenges(user_id,purpose)",
        );
        $db->exec(
            "CREATE INDEX two_factor_expiry ON two_factor_challenges(expires_at)",
        );
    },
    "down" => function (PDO $db): void {
        $db->exec("DROP TABLE two_factor_challenges");
        $db->exec("ALTER TABLE users DROP COLUMN two_factor_enabled");
    },
];
