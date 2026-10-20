<?php
declare(strict_types=1);
require_once __DIR__ . "/blocks.php";
require_once __DIR__ . "/../models/Content.php";
require_once __DIR__ . "/../models/Media.php";
require_once __DIR__ . "/../models/SeoMetadata.php";
final class PageData
{
    public array $config;
    private SeoMetadata $metadata;
    private array $routes;
    private array $imageCache = [];
    public function __construct()
    {
        $this->config = require __DIR__ . "/../seo.config.php";
        $db = require __DIR__ . "/../db.php";
        $this->metadata = new SeoMetadata($db);
        $this->routes = SeoMetadata::routes();
        $settings = $this->metadata->settings();
        if ($settings) {
            foreach (
                [
                    "site_name" => "name",
                    "description" => "description",
                    "default_image" => "default_image",
                    "language" => "language",
                    "locale" => "locale",
                ]
                as $from => $to
            ) {
                if ($settings[$from] !== "") {
                    $this->config[$to] = $settings[$from];
                }
            }
            $this->config["allow_indexing"] =
                $this->config["allow_indexing"] && !$settings["noindex"];
        }
        if (
            !filter_var($this->config["site_url"], FILTER_VALIDATE_URL) ||
            !in_array(
                parse_url($this->config["site_url"], PHP_URL_SCHEME),
                ["http", "https"],
                true,
            )
        ) {
            throw new RuntimeException(
                "Configure a valid site_url in php/seo.config.php.",
            );
        }
    }
    public function url(string $path): string
    {
        return $this->config["site_url"] . $path;
    }
    public function indexable(): bool
    {
        return $this->config["allow_indexing"] &&
            !in_array(
                strtolower(
                    parse_url($this->config["site_url"], PHP_URL_HOST) ?? "",
                ),
                ["localhost", "127.0.0.1", "::1"],
                true,
            );
    }
    private function base(
        string $path,
        string $title,
        string $description,
        int $status = 200,
    ): array {
        $private = (bool) preg_match(
            '#^/(site-admin|admin|dashboard)(/|$)#',
            $path,
        );
        $displayTitle = $title;
        $intro = $description;
        $metadata =
            !$private && $status === 200 && isset($this->routes[$path])
                ? $this->metadata->page($path)
                : null;
        $title = $metadata["title"] ?? "" ?: $title;
        $description =
            $metadata["description"] ?? "" ?:
            ($description ?:
            $this->config["description"]);
        $index =
            $this->indexable() &&
            !$private &&
            $status === 200 &&
            !($metadata["noindex"] ?? false) &&
            !($this->routes[$path]["noindex"] ?? false);
        return [
            "status" => $status,
            "type" => $private ? "private" : "static",
            "path" => $path,
            "heading" => $displayTitle,
            "intro" => $intro,
            "seo" => [
                "title" => $title,
                "description" => $description,
                "canonical" =>
                    $private || $status !== 200 ? null : $this->url($path),
                "robots" => $index
                    ? "index, follow, max-image-preview:large"
                    : "noindex, " . ($private ? "nofollow" : "follow"),
                "image" =>
                    $metadata["image"] ?? "" ?: $this->config["default_image"],
                "siteName" => $this->config["name"],
                "locale" => $this->config["locale"],
                "language" => $this->config["language"],
                "ogType" => "website",
                "schema" => [],
            ],
        ];
    }
    private function organization(): array
    {
        return [
            "@type" => "RealEstateAgent",
            "@id" => $this->url("/#organization"),
            "name" => $this->config["name"],
            "url" => $this->url("/"),
            "areaServed" => ["@type" => "City", "name" => "Lagos"],
        ];
    }
    private function schema(
        array &$page,
        string $type = "WebPage",
        array $extra = [],
    ): void {
        if (!$page["seo"]["canonical"]) {
            return;
        }
        $canonical = $page["seo"]["canonical"];
        $graph = [
            $this->organization(),
            [
                "@type" => "WebSite",
                "@id" => $this->url("/#website"),
                "url" => $this->url("/"),
                "name" => $this->config["name"],
                "publisher" => ["@id" => $this->url("/#organization")],
            ],
            array_merge(
                [
                    "@type" => $type,
                    "@id" => $canonical . "#page",
                    "url" => $canonical,
                    "name" => $page["seo"]["title"],
                    "description" => $page["seo"]["description"],
                    "isPartOf" => ["@id" => $this->url("/#website")],
                ],
                $extra,
            ),
        ];
        if ($type === "Event") {
            $graph[2] = [
                "@type" => "WebPage",
                "@id" => $canonical . "#page",
                "url" => $canonical,
                "name" => $page["seo"]["title"],
                "description" => $page["seo"]["description"],
                "isPartOf" => ["@id" => $this->url("/#website")],
                "mainEntity" => ["@id" => $canonical . "#event"],
            ];
            $graph[] = array_merge(
                [
                    "@type" => "Event",
                    "@id" => $canonical . "#event",
                    "url" => $canonical,
                    "description" => $page["seo"]["description"],
                ],
                $extra,
            );
        }
        $crumbs = [
            [
                "@type" => "ListItem",
                "position" => 1,
                "name" => "Home",
                "item" => $this->url("/"),
            ],
        ];
        if ($page["path"] !== "/") {
            $segments = explode("/", trim($page["path"], "/"));
            $labels = ["about" => "About us", "contact" => "Contact"];
            foreach (ContentModules::all() as $module) {
                $labels[$module["path"]] = $module["label"];
            }
            $base = "/" . $segments[0];
            $crumbs[] = [
                "@type" => "ListItem",
                "position" => 2,
                "name" => $labels[$segments[0]] ?? $page["heading"],
                "item" => count($segments) > 1 ? $this->url($base) : $canonical,
            ];
            if (count($segments) > 1) {
                $crumbs[] = [
                    "@type" => "ListItem",
                    "position" => 3,
                    "name" => $page["heading"],
                    "item" => $canonical,
                ];
            }
        }
        $graph[] = ["@type" => "BreadcrumbList", "itemListElement" => $crumbs];
        $page["seo"]["schema"] = [
            "@context" => "https://schema.org",
            "@graph" => $graph,
        ];
    }
    private function loadImages(PDO $db, array $ids): void
    {
        $ids = array_values(
            array_unique(
                array_filter(
                    array_map("intval", $ids),
                    fn($id) => $id > 0 &&
                        !array_key_exists($id, $this->imageCache),
                ),
            ),
        );
        if (!$ids) {
            return;
        }
        $query = $db->prepare(
            "SELECT id,filename,alt_text,title,width,height FROM media WHERE id IN (" .
                implode(",", array_fill(0, count($ids), "?")) .
                ")",
        );
        $query->execute($ids);
        foreach ($ids as $id) {
            $this->imageCache[$id] = null;
        }
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->imageCache[(int) $row["id"]] = [
                "url" => "/uploads/" . $row["filename"],
                "alt" => $row["alt_text"] ?: $row["title"],
                "width" => (int) $row["width"],
                "height" => (int) $row["height"],
            ];
        }
    }
    private function image(PDO $db, ?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $this->loadImages($db, [$id]);
        return $this->imageCache[$id];
    }
    public function resolve(string $requestPath, string $query = ""): array
    {
        if (
            strlen($requestPath) > 1000 ||
            !str_starts_with($requestPath, "/")
        ) {
            return $this->base(
                "/not-found",
                "Page not found | " . $this->config["name"],
                "The page you requested could not be found.",
                404,
            );
        }
        $path = rtrim($requestPath, "/") ?: "/";
        $brand = $this->config["name"];
        if (preg_match('#^/(site-admin|admin|dashboard)(/|$)#', $path)) {
            return $this->base(
                $path,
                "Sign in | " . $brand,
                "Private site administration.",
            );
        }
        if (
            isset($this->routes[$path]) &&
            !in_array(ltrim($path, "/"), ContentModules::paths(), true)
        ) {
            $route = $this->routes[$path];
            $title = str_replace(
                "{siteName}",
                $brand,
                $route["title"] ?? $route["label"] . " | " . $brand,
            );
            $description =
                $route["description"] ?? $this->config["description"];
            $page = $this->base($path, $title, $description);
            $page["heading"] = $route["heading"] ?? $route["label"];
            $this->schema($page, $route["schema"] ?? "WebPage");
            return $page;
        }
        $kinds = array_flip(ContentModules::paths());
        if (
            preg_match(
                '#^/([a-z][a-z0-9-]*)(?:/([a-z0-9]+(?:-[a-z0-9]+)*))?$#D',
                $path,
                $match,
            ) &&
            isset($kinds[$match[1]])
        ) {
            $kind = $kinds[$match[1]];
            $definition = ContentModules::get($kind);
            $label = $definition["label"];
            $profile = $definition["profile"];
            $db = require __DIR__ . "/../db.php";
            $table = crudIdentifier($db, $kind);
            if (!isset($match[2])) {
                if (!isset($this->routes[$path])) {
                    return $this->base(
                        $path,
                        "Page not found | " . $brand,
                        "This page does not exist.",
                        404,
                    );
                }
                parse_str($query, $params);
                $number = filter_var($params["page"] ?? 1, FILTER_VALIDATE_INT);
                if ($number === false || $number < 1) {
                    return $this->base(
                        $path,
                        "Page not found | " . $brand,
                        "This page does not exist.",
                        404,
                    );
                }
                $visible =
                    "status IN ('published','scheduled') AND publish_at<=?";
                $now = gmdate("Y-m-d H:i:s");
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE {$visible}",
                );
                $stmt->execute([$now]);
                $total = (int) $stmt->fetchColumn();
                $pages = max(1, (int) ceil($total / 12));
                if ($number > $pages) {
                    return $this->base(
                        $path,
                        "Page not found | " . $brand,
                        "This page does not exist.",
                        404,
                    );
                }
                $offset = ($number - 1) * 12;
                $stmt = $db->prepare(
                    "SELECT * FROM {$table} WHERE {$visible} ORDER BY publish_at DESC,id DESC LIMIT 12 OFFSET {$offset}",
                );
                $stmt->execute([$now]);
                $rows = [];
                $model = new Content($db, $kind);
                $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->loadImages(
                    $db,
                    array_column($entries, "featured_media_id"),
                );
                foreach ($entries as $entry) {
                    $item = $model->decode($entry);
                    $item["image"] = $this->image(
                        $db,
                        $item["featured_media_id"],
                    );
                    $item["url"] = "/" . $match[1] . "/" . $item["slug"];
                    unset($item["content"]);
                    $rows[] = $item;
                }
                $page = $this->base(
                    $path,
                    $label .
                        ($number > 1 ? " – Page " . $number : "") .
                        " | " .
                        $brand,
                    $definition["description"] ?: $this->config["description"],
                );
                $page["seo"]["canonical"] =
                    $this->url($path) . ($number > 1 ? "?page=" . $number : "");
                $page["heading"] = $label;
                $page["type"] = "listing";
                $page["kind"] = $kind;
                $page["items"] = $rows;
                $page["pagination"] = [
                    "page" => $number,
                    "pages" => $pages,
                    "total" => $total,
                    "perPage" => 12,
                ];
                $this->schema($page, "CollectionPage", [
                    "mainEntity" => [
                        "@type" => "ItemList",
                        "itemListElement" => array_map(
                            fn($item, $index) => [
                                "@type" => "ListItem",
                                "position" => $offset + $index + 1,
                                "url" => $this->url($item["url"]),
                                "name" => $item["title"],
                            ],
                            $rows,
                            array_keys($rows),
                        ),
                    ],
                ]);
                return $page;
            }
            $model = new Content($db, $kind);
            $item = $model->publicBySlug($match[2]);
            if (!$item) {
                $stmt = $db->prepare(
                    "SELECT new_slug FROM seo_redirects WHERE kind=? AND old_slug=?",
                );
                $stmt->execute([$kind, $match[2]]);
                $new = $stmt->fetchColumn();
                if ($new && $model->publicBySlug($new)) {
                    $page = $this->base($path, "Redirect", "", 301);
                    $page["redirect"] = "/" . $match[1] . "/" . $new;
                    return $page;
                }
                return $this->base(
                    $path,
                    "Page not found | " . $brand,
                    "This page does not exist or is not published.",
                    404,
                );
            }
            $html = seoBlocks($item["content"]);
            $description =
                $item["seo_description"] ?:
                seoShort(
                    $item["excerpt"] ?:
                    html_entity_decode(strip_tags($html), ENT_QUOTES, "UTF-8"),
                    160,
                );
            if (!$description) {
                $description = $this->config["description"];
            }
            $page = $this->base(
                $path,
                $item["seo_title"] ?:
                seoShort($item["title"], 45) . " | " . $brand,
                $description,
            );
            $page["heading"] = $item["title"];
            $page["type"] = "detail";
            $page["kind"] = $kind;
            $page["html"] = $html;
            $page["item"] = $item;
            $this->loadImages($db, [
                $item["featured_media_id"],
                ...$item["details"]["gallery_ids"] ?? [],
            ]);
            $page["image"] = $this->image($db, $item["featured_media_id"]);
            $page["gallery"] = [];
            foreach ($item["details"]["gallery_ids"] ?? [] as $id) {
                $image = $this->image($db, $id);
                if ($image) {
                    $page["gallery"][] = $image;
                }
            }
            if ($page["image"]) {
                $page["seo"]["image"] = $this->url($page["image"]["url"]);
            }
            if ($item["seo_noindex"]) {
                $page["seo"]["robots"] = "noindex, follow";
            }
            $date = fn($value) => str_replace(" ", "T", $value) . "Z";
            $extra = [];
            $type = "WebPage";
            if ($profile === "service") {
                $extra = [
                    "mainEntity" => [
                        "@type" => "Service",
                        "@id" => $page["seo"]["canonical"] . "#service",
                        "name" => $item["title"],
                        "description" => $page["seo"]["description"],
                        "url" => $page["seo"]["canonical"],
                        "provider" => ["@id" => $this->url("/#organization")],
                    ],
                ];
            }
            if ($profile === "article") {
                $type = "Article";
                $page["seo"]["ogType"] = "article";
                $extra = [
                    "headline" => $item["title"],
                    "image" => [$page["seo"]["image"]],
                    "datePublished" => $date($item["publish_at"]),
                    "dateModified" => $date(
                        max($item["updated_at"], $item["publish_at"]),
                    ),
                    "author" => ["@type" => "Organization", "name" => $brand],
                    "publisher" => ["@id" => $this->url("/#organization")],
                ];
            }
            if ($profile === "event") {
                $type = "Event";
                $extra = [
                    "name" => $item["title"],
                    "eventStatus" => "https://schema.org/EventScheduled",
                    "eventAttendanceMode" =>
                        "https://schema.org/OfflineEventAttendanceMode",
                    "image" => [$page["seo"]["image"]],
                    "location" => [
                        "@type" => "Place",
                        "name" => $item["details"]["location"],
                        "address" => $item["details"]["location"],
                    ],
                    "organizer" => ["@id" => $this->url("/#organization")],
                ];
                if ($item["details"]["starts_at"]) {
                    $extra["startDate"] = $date($item["details"]["starts_at"]);
                }
                if ($item["details"]["ends_at"]) {
                    $extra["endDate"] = $date($item["details"]["ends_at"]);
                }
            }
            if ($profile === "property") {
                $type = "RealEstateListing";
                $d = $item["details"];
                $extra = [
                    "datePosted" => $date($item["publish_at"]),
                    "image" => [$page["seo"]["image"]],
                    "about" => [
                        "@type" => "Accommodation",
                        "name" => $item["title"],
                        "address" => $d["location"],
                        "numberOfBedrooms" => $d["bedrooms"],
                        "numberOfBathroomsTotal" => $d["bathrooms"],
                    ],
                    "offers" => [
                        "@type" => "Offer",
                        "url" => $page["seo"]["canonical"],
                        "price" => $d["price"],
                        "priceCurrency" => $d["currency"],
                        "availability" => in_array(
                            $d["availability"],
                            ["sold", "rented"],
                            true,
                        )
                            ? "https://schema.org/SoldOut"
                            : "https://schema.org/InStock",
                        "businessFunction" =>
                            $d["listing_type"] === "rent"
                                ? "http://purl.org/goodrelations/v1#LeaseOut"
                                : "http://purl.org/goodrelations/v1#Sell",
                    ],
                ];
            }
            $this->schema($page, $type, $extra);
            return $page;
        }
        return $this->base(
            $path,
            "Page not found | " . $brand,
            "The page you requested could not be found.",
            404,
        );
    }
}
