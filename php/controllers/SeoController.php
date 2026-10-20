<?php
declare(strict_types=1);
require_once __DIR__ . "/../seo/PageData.php";
require_once __DIR__ . "/../http/response.php";
final class SeoController
{
    public function page(): void
    {
        $path = $_GET["path"] ?? "/";
        if (!is_string($path) || strlen($path) > 2000) {
            throw new InvalidArgumentException("Invalid page path.");
        }
        $parts = parse_url($path);
        if (
            $parts === false ||
            isset($parts["host"]) ||
            isset($parts["scheme"])
        ) {
            throw new InvalidArgumentException("Use a relative path.");
        }
        $page = (new PageData())->resolve(
            $parts["path"] ?? "/",
            $parts["query"] ?? "",
        );
        header("Cache-Control: no-store");
        jsonResponse(
            ["success" => true, ...$page],
            $page["status"] === 301 ? 200 : $page["status"],
        );
    }
    public function robots(): void
    {
        $site = new PageData();
        header("Content-Type: text/plain; charset=utf-8");
        echo "User-agent: *\n" .
            ($site->indexable() ? "Disallow: /api/\n" : "Disallow: /\n") .
            "Sitemap: " .
            $site->url("/sitemap.xml") .
            "\n";
    }
    public function sitemap(): void
    {
        $path = $_GET["path"] ?? "/sitemap.xml";
        if (
            !is_string($path) ||
            !preg_match('#^/sitemap(?:-[a-z0-9_-]+)?\.xml$#D', $path)
        ) {
            throw new InvalidArgumentException("Invalid sitemap path.");
        }
        require_once __DIR__ . "/../seo/sitemap.php";
        sitemapResponse($path);
    }
}
