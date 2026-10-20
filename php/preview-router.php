<?php
// Production-like local preview: php -S 127.0.0.1:8001 -t dist php/preview-router.php
$root = realpath(__DIR__ . "/../dist");
if (!$root) {
    http_response_code(503);
    exit("Run npm run build first.");
}
$path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?: "/");
if (preg_match("#(?:^|/)\.#", $path)) {
    http_response_code(404);
    return;
}
if (preg_match('#^/api(?:/|$)#', $path)) {
    require $root . "/api/index.php";
    return;
}
$file = realpath($root . $path);
if (
    $file &&
    str_starts_with($file, $root . DIRECTORY_SEPARATOR) &&
    is_file($file) &&
    !str_ends_with($file, ".php") &&
    !str_ends_with($file, "index.html")
) {
    return false;
}
require $root . "/index.php";
