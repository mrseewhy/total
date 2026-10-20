<?php
declare(strict_types=1);
ini_set("display_errors", "0");
try {
    require_once __DIR__ . "/../php/http/security.php";
    securityHeaders();
    require_once __DIR__ . "/../php/maintenance/Mode.php";
    if (!MaintenanceMode::enterRequest(false)) {
        return;
    }
    require_once __DIR__ . "/../php/seo/PageData.php";
    require_once __DIR__ . "/../php/seo/head.php";
    require_once __DIR__ . "/../php/seo/body.php";
    $path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
    $query = $_SERVER["QUERY_STRING"] ?? "";
    if (
        strlen($path) > 2000 ||
        str_contains($path, "\\") ||
        str_starts_with($path, "//") ||
        preg_match('/[\x00-\x20]/', $path)
    ) {
        http_response_code(404);
        header("X-Robots-Tag: noindex");
        echo "Page not found.";
        return;
    }
    if (!in_array($_SERVER["REQUEST_METHOD"] ?? "GET", ["GET", "HEAD"], true)) {
        http_response_code(405);
        header("Allow: GET, HEAD");
        header("X-Robots-Tag: noindex");
        echo "Method not allowed";
        return;
    }
    $site = new PageData();
    if ($path === "/robots.txt") {
        header("Content-Type: text/plain; charset=utf-8");
        echo "User-agent: *\n" .
            ($site->indexable() ? "Disallow: /api/\n" : "Disallow: /\n") .
            "Sitemap: " .
            $site->url("/sitemap.xml") .
            "\n";
        return;
    }
    if (preg_match('#^/sitemap(?:-[a-z0-9_-]+)?\.xml$#D', $path)) {
        require_once __DIR__ . "/../php/seo/sitemap.php";
        sitemapResponse($path);
        return;
    }
    if (in_array($path, ["/index.html", "/index.php"], true)) {
        header("Location: /", true, 301);
        return;
    }
    if ($path !== "/" && str_ends_with($path, "/")) {
        header(
            "Location: " . rtrim($path, "/") . ($query ? "?" . $query : ""),
            true,
            301,
        );
        return;
    }
    $page = $site->resolve($path, $query);
    if (isset($page["redirect"])) {
        header("Location: " . $page["redirect"], true, 301);
        return;
    }
    http_response_code($page["status"]);
    header("Content-Type: text/html; charset=utf-8");
    header(
        "Cache-Control: " .
            ($page["type"] === "private" ? "no-store" : "no-cache"),
    );
    if (str_starts_with($page["seo"]["robots"], "noindex")) {
        header("X-Robots-Tag: " . $page["seo"]["robots"]);
    }
    $template = is_file(__DIR__ . "/index.html")
        ? file_get_contents(__DIR__ . "/index.html")
        : null;
    if (!$template) {
        http_response_code(503);
        header("X-Robots-Tag: noindex");
        echo "Build the frontend with npm run build before serving this folder.";
        return;
    }
    $template = preg_replace_callback(
        "#<!--seo:start-->.*?<!--seo:end-->#s",
        fn() => seoHead($page["seo"]),
        $template,
    );
    $template = preg_replace_callback(
        '/(<html\b[^>]*\blang=")[^"]*(")/i',
        fn($match) => $match[1] .
            seoEscape($page["seo"]["language"]) .
            $match[2],
        $template,
        1,
    );
    $template = str_replace("<!--app-html-->", seoBody($page), $template);
    $bootstrap = json_encode(
        ["success" => true, ...$page],
        JSON_THROW_ON_ERROR |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_UNESCAPED_SLASHES,
    );
    $template = str_replace(
        "</head>",
        '<script id="seo-page-data" type="application/json">' .
            $bootstrap .
            "</script></head>",
        $template,
    );
    echo $template;
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(503);
    header("X-Robots-Tag: noindex");
    header("Retry-After: 60");
    echo '<!doctype html><html lang="en"><head><title>Temporarily unavailable</title><meta name="robots" content="noindex"></head><body><h1>Temporarily unavailable</h1><p>Please try again shortly.</p></body></html>';
}
