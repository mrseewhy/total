<?php
return [
    "up" => function (PDO $db): void {
        foreach (["posts", "events", "properties"] as $table) {
            $db->exec(
                "ALTER TABLE {$table} ADD COLUMN seo_title VARCHAR(70) NOT NULL DEFAULT ''",
            );
            $db->exec(
                "ALTER TABLE {$table} ADD COLUMN seo_description VARCHAR(170) NOT NULL DEFAULT ''",
            );
            $db->exec(
                "ALTER TABLE {$table} ADD COLUMN seo_noindex INTEGER NOT NULL DEFAULT 0",
            );
        }
        $db->exec(
            "CREATE TABLE seo_redirects (kind VARCHAR(20) NOT NULL, old_slug VARCHAR(200) NOT NULL, new_slug VARCHAR(200) NOT NULL, PRIMARY KEY(kind,old_slug))",
        );
    },
    "down" => function (PDO $db): void {
        $db->exec("DROP TABLE seo_redirects");
        foreach (["posts", "events", "properties"] as $table) {
            foreach (
                ["seo_title", "seo_description", "seo_noindex"]
                as $column
            ) {
                $db->exec("ALTER TABLE {$table} DROP COLUMN {$column}");
            };
        }
    },
];
