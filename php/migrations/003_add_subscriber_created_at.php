<?php
return [
    'up' => function (PDO $db): void {
        $db->exec('ALTER TABLE subscribers ADD COLUMN created_at TIMESTAMP NULL');
    },
    'down' => function (PDO $db): void {
        $db->exec('ALTER TABLE subscribers DROP COLUMN created_at');
    },
];
