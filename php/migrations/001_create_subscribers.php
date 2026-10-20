<?php

return [
    'up' => function (PDO $db): void {
        $db->exec("
            CREATE TABLE subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE
            )
        ");
    },

    'down' => function (PDO $db): void {
        $db->exec("DROP TABLE subscribers");
    },
];
