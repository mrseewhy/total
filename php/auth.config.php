<?php
declare(strict_types=1);
$config = [
    // Use your real HTTPS website URL in production. Never derive this from the Host header.
    "app_url" => getenv("APP_URL") ?: "http://localhost:5173",
    // Legacy sender fallback. Prefer from_address in mail.config.local.php.
    "mail_from" => getenv("MAIL_FROM") ?: "",
    // Set true on HTTPS hosting (also enabled automatically when PHP detects HTTPS).
    "secure_cookie" => getenv("SESSION_SECURE") === "1",
];

$local = __DIR__ . "/auth.config.local.php";
return is_file($local) ? array_replace($config, require $local) : $config;
