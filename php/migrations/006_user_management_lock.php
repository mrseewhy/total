<?php
return [
    'up'=>function(PDO $db):void {
        $db->exec('CREATE TABLE user_management_lock (id INTEGER PRIMARY KEY)');
        $db->exec('INSERT INTO user_management_lock (id) VALUES (1)');
    },
    'down'=>function(PDO $db):void { $db->exec('DROP TABLE user_management_lock'); },
];
