<?php
declare(strict_types=1);
$auth = require __DIR__ . "/auth.config.php";
$config = [
    "site_url" => rtrim(getenv("SITE_URL") ?: $auth["app_url"], "/"),
    "name" => "Haven Real Estate",
    "language" => "en",
    "locale" => "en_NG",
    "description" =>
        "Find homes for sale and rent in Lagos with Haven Real Estate. Explore properties, local insights, and upcoming events.",
    "default_image" =>
        "https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1200&h=630&q=80",
    // Set SEO_INDEXING=0 for staging. Localhost is always noindex.
    "allow_indexing" => getenv("SEO_INDEXING") !== "0",
];

$local = __DIR__ . '/seo.config.local.php';
return is_file($local) ? array_replace($config, require $local) : $config;
