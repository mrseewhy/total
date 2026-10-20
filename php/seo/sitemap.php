<?php
declare(strict_types=1);
require_once __DIR__ . "/PageData.php";
function sitemapResponse(string $path): void
{
    $site = new PageData();
    $db = require __DIR__ . "/../db.php";
    $now = gmdate("Y-m-d H:i:s");
    header("Cache-Control: no-cache");
    if (!$site->indexable()) {
        header("Content-Type: application/xml; charset=utf-8");
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
        return;
    }
    $visible =
        "status IN ('published','scheduled') AND publish_at<=? AND seo_noindex=0";
    $kinds = ContentModules::paths();
    header("Content-Type: application/xml; charset=utf-8");
    header("Cache-Control: no-cache");
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    if ($path === "/sitemap.xml") {
        $xml .=
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>' .
            seoEscape($site->url("/sitemap-pages.xml")) .
            "</loc></sitemap>";
        foreach ($kinds as $table => $route) {
            $quoted = crudIdentifier($db, $table);
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM {$quoted} WHERE {$visible}",
            );
            $stmt->execute([$now]);
            $pages = max(1, (int) ceil((int) $stmt->fetchColumn() / 5000));
            for ($i = 1; $i <= $pages; $i++) {
                $xml .=
                    "<sitemap><loc>" .
                    seoEscape(
                        $site->url("/sitemap-" . $table . "-" . $i . ".xml"),
                    ) .
                    "</loc></sitemap>";
            }
        }
        $xml .= "</sitemapindex>";
    } else {
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        if ($path === "/sitemap-pages.xml") {
            foreach (array_keys(SeoMetadata::pages()) as $url) {
                $page = $site->resolve($url);
                if (str_starts_with($page["seo"]["robots"], "noindex")) {
                    continue;
                }
                $xml .=
                    "<url><loc>" . seoEscape($site->url($url)) . "</loc></url>";
            }
            foreach ($kinds as $table => $route) {
                $quoted = crudIdentifier($db, $table);
                if (!isset(SeoMetadata::pages()["/" . $route])) {
                    continue;
                }
                if (
                    str_starts_with(
                        $site->resolve("/" . $route)["seo"]["robots"],
                        "noindex",
                    )
                ) {
                    continue;
                }
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM {$quoted} WHERE status IN ('published','scheduled') AND publish_at<=?",
                );
                $stmt->execute([$now]);
                $pages = (int) ceil((int) $stmt->fetchColumn() / 12);
                for ($i = 2; $i <= $pages; $i++) {
                    $xml .=
                        "<url><loc>" .
                        seoEscape($site->url("/" . $route . "?page=" . $i)) .
                        "</loc></url>";
                }
            }
        } elseif (
            preg_match(
                '#^/sitemap-([a-z][a-z0-9_]*)-([1-9][0-9]*)\.xml$#D',
                $path,
                $match,
            )
        ) {
            $table = $match[1];
            if (!isset($kinds[$table])) {
                http_response_code(404);
                echo $xml . "</urlset>";
                return;
            }
            $page = filter_var($match[2], FILTER_VALIDATE_INT);
            if ($page === false || $page > 1000000) {
                http_response_code(404);
                echo $xml . "</urlset>";
                return;
            }
            $offset = ($page - 1) * 5000;
            $quoted = crudIdentifier($db, $table);
            $stmt = $db->prepare(
                "SELECT slug,updated_at,publish_at FROM {$quoted} WHERE {$visible} ORDER BY id ASC LIMIT 5000 OFFSET {$offset}",
            );
            $stmt->execute([$now]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows && $page > 1) {
                http_response_code(404);
            }
            foreach ($rows as $row) {
                $last = max($row["updated_at"], $row["publish_at"]);
                $xml .=
                    "<url><loc>" .
                    seoEscape(
                        $site->url("/" . $kinds[$table] . "/" . $row["slug"]),
                    ) .
                    "</loc><lastmod>" .
                    str_replace(" ", "T", $last) .
                    "Z</lastmod></url>";
            }
        } else {
            http_response_code(404);
        }
        $xml .= "</urlset>";
    }
    echo $xml;
}
