<?php

declare(strict_types=1);

return [
    'up' => function (PDO $db): void {
        $db->exec("
            CREATE TABLE rate_limits (
                identifier VARCHAR(64) PRIMARY KEY,
                attempts INTEGER NOT NULL,
                window_start BIGINT NOT NULL
            )
        ");
    },

    'down' => function (PDO $db): void {
        $db->exec("DROP TABLE rate_limits");
    },
];
