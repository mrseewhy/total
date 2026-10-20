<?php
declare(strict_types=1);
$auth = require __DIR__ . "/auth.config.php";
$config = [
    // Enable only after the host has configured PHP mail() and accepted your sender.
    "enabled" => getenv("MAIL_ENABLED") === "1",
    "transport" => "mail",
    "from_address" => getenv("MAIL_FROM") ?: $auth["mail_from"] ?? "",
    "from_name" => getenv("MAIL_FROM_NAME") ?: "Haven",
    "reply_to" => getenv("MAIL_REPLY_TO") ?: "",
];
$local = __DIR__ . "/mail.config.local.php";
return is_file($local) ? array_replace($config, require $local) : $config;
