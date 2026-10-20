<?php
declare(strict_types=1);
require_once __DIR__ . "/Validator.php";
require_once __DIR__ . "/../config/ContentModules.php";
final class ContentValidation
{
    public static function text(
        array $data,
        string $key,
        int $max,
        bool $required = false,
    ): string {
        return Validator::field($key, function () use (
            $data,
            $key,
            $max,
            $required,
        ) {
            $v = $data[$key] ?? "";
            if (!is_string($v)) {
                throw new ValidationException([
                    "details." . $key => "Invalid {$key}.",
                ]);
            }
            $v = trim($v);
            if ($required && $v === "") {
                throw new InvalidArgumentException(
                    ucfirst($key) . " is required.",
                );
            }
            Validator::maxLength($v, $max, ucfirst($key) . " is too long.");
            return $v;
        });
    }
    public static function date(mixed $value, string $name): string
    {
        $field = match ($name) {
            "publication date" => "publish_at",
            "start date" => "details.starts_at",
            "end date" => "details.ends_at",
            default => $name,
        };
        if (
            !is_string($value) ||
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/D',
                $value,
            )
        ) {
            throw new ValidationException([
                $field => "Invalid {$name}; use a UTC ISO date.",
            ]);
        }
        $date = DateTimeImmutable::createFromFormat(
            str_contains($value, ".") ? "Y-m-d\TH:i:s.v\Z" : "Y-m-d\TH:i:s\Z",
            $value,
            new DateTimeZone("UTC"),
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date ||
            ($errors && ($errors["warning_count"] || $errors["error_count"]))
        ) {
            throw new ValidationException([$field => "Invalid {$name}."]);
        }
        return $date->format("Y-m-d H:i:s");
    }
    public static function ids(mixed $ids): array
    {
        if (!is_array($ids) || !array_is_list($ids) || count($ids) > 100) {
            throw new InvalidArgumentException("Select at most 100 items.");
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new InvalidArgumentException("Invalid selection.");
            }
        }
        return array_values(array_unique($ids));
    }
    public static function blocks(mixed $blocks): array
    {
        if (
            !is_array($blocks) ||
            !array_is_list($blocks) ||
            strlen(json_encode($blocks, JSON_THROW_ON_ERROR)) > 1000000
        ) {
            throw new InvalidArgumentException("Invalid or oversized content.");
        }
        $count = 0;
        self::walk($blocks, $count, 0);
        return $blocks;
    }
    private static function walk(array $nodes, int &$count, int $depth): void
    {
        if ($depth > 20) {
            throw new InvalidArgumentException("Content is too deeply nested.");
        }
        foreach ($nodes as $node) {
            if (++$count > 10000 || !is_array($node)) {
                throw new InvalidArgumentException("Invalid content.");
            }
            if (
                isset($node["type"]) &&
                !in_array(
                    $node["type"],
                    [
                        "paragraph",
                        "heading",
                        "bulletListItem",
                        "numberedListItem",
                        "checkListItem",
                        "quote",
                        "codeBlock",
                        "divider",
                        "image",
                        "table",
                        "text",
                        "link",
                        "tableContent",
                        "tableCell",
                    ],
                    true,
                )
            ) {
                throw new InvalidArgumentException(
                    "Unsupported content block.",
                );
            }
            foreach ($node as $key => $value) {
                if (in_array($key, ["url", "href"], true) && $value !== "") {
                    if (!is_string($value) || strlen($value) > 2000) {
                        throw new InvalidArgumentException(
                            "Invalid content link.",
                        );
                    }
                    if (
                        $key === "url" &&
                        !preg_match(
                            '#^/uploads/[a-f0-9]{32}\.(jpg|png|webp|gif)$#D',
                            $value,
                        )
                    ) {
                        throw new InvalidArgumentException(
                            "Choose images from the media library.",
                        );
                    }
                    if (
                        $key === "href" &&
                        !preg_match(
                            "#^(https?://|mailto:|tel:|/(?!/)|\#)#i",
                            $value,
                        )
                    ) {
                        throw new InvalidArgumentException("Unsafe link.");
                    }
                }
                if (is_array($value)) {
                    self::walkValue($value, $count, $depth + 1);
                }
            }
        }
    }
    private static function walkValue(
        array $value,
        int &$count,
        int $depth,
    ): void {
        if (array_is_list($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    self::walk([$item], $count, $depth);
                }
            }
        } else {
            self::walk([$value], $count, $depth);
        }
    }
    public static function validate(array $data, string $kind): array
    {
        $module = ContentModules::get($kind);
        $title = self::text($data, "title", 200, true);
        $slug = self::text($data, "slug", 200, true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new ValidationException([
                "slug" =>
                    "Slug must use lowercase letters, numbers, and hyphens.",
            ]);
        }
        $status = $data["status"] ?? "draft";
        if (
            !in_array(
                $status,
                $module["scheduling"]
                    ? ["draft", "scheduled", "published"]
                    : ["draft", "published"],
                true,
            )
        ) {
            throw new ValidationException([
                "status" => "Invalid publication status.",
            ]);
        }
        $publish = null;
        if ($status === "scheduled") {
            $publish = self::date(
                $data["publish_at"] ?? null,
                "publication date",
            );
            if ($publish <= gmdate("Y-m-d H:i:s")) {
                throw new ValidationException([
                    "publish_at" => "Choose a future publication time.",
                ]);
            }
        }
        if ($status === "published") {
            $publish = gmdate("Y-m-d H:i:s");
        }
        $featured = $data["featured_media_id"] ?? null;
        if ($featured !== null && (!is_int($featured) || $featured < 1)) {
            throw new ValidationException([
                "featured_media_id" => "Invalid featured image.",
            ]);
        }
        $details = $data["details"] ?? [];
        if (!is_array($details)) {
            throw new InvalidArgumentException("Invalid details.");
        }
        $clean = [];
        if ($module["profile"] === "event") {
            $clean["location"] = Validator::field(
                "details.location",
                fn() => self::text(
                    $details,
                    "location",
                    300,
                    $status !== "draft",
                ),
            );
            $clean["starts_at"] = empty($details["starts_at"])
                ? null
                : self::date($details["starts_at"], "start date");
            $clean["ends_at"] = empty($details["ends_at"])
                ? null
                : self::date($details["ends_at"], "end date");
            if ($status !== "draft" && !$clean["starts_at"]) {
                throw new ValidationException([
                    "details.starts_at" => "Event start date is required.",
                ]);
            }
            if (
                $clean["ends_at"] &&
                (!$clean["starts_at"] ||
                    $clean["ends_at"] <= $clean["starts_at"])
            ) {
                throw new ValidationException([
                    "details.ends_at" => "Event end must be after its start.",
                ]);
            }
        }
        if ($module["profile"] === "property") {
            $clean["location"] = Validator::field(
                "details.location",
                fn() => self::text(
                    $details,
                    "location",
                    300,
                    $status !== "draft",
                ),
            );
            $price = $details["price"] ?? "";
            if (
                !is_string($price) ||
                !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $price)
            ) {
                throw new ValidationException([
                    "details.price" =>
                        "Enter a valid non-negative price with up to two decimals.",
                ]);
            }
            $clean["price"] = $price;
            $clean["currency"] = $details["currency"] ?? "NGN";
            if (
                !is_string($clean["currency"]) ||
                !preg_match('/^[A-Z]{3}$/D', $clean["currency"])
            ) {
                throw new ValidationException([
                    "details.currency" => "Use a three-letter currency code.",
                ]);
            }
            foreach (
                [
                    "listing_type" => ["sale", "rent"],
                    "availability" => [
                        "available",
                        "under_offer",
                        "sold",
                        "rented",
                    ],
                ]
                as $key => $choices
            ) {
                $clean[$key] = $details[$key] ?? null;
                if (!in_array($clean[$key], $choices, true)) {
                    throw new ValidationException([
                        "details." . $key => "Invalid {$key}.",
                    ]);
                }
            }
            foreach (["bedrooms", "bathrooms"] as $key) {
                $clean[$key] = $details[$key] ?? 0;
                if (
                    !is_int($clean[$key]) ||
                    $clean[$key] < 0 ||
                    $clean[$key] > 100
                ) {
                    throw new ValidationException([
                        "details." . $key => "Invalid {$key}.",
                    ]);
                }
            }
            $clean["gallery_ids"] = self::ids($details["gallery_ids"] ?? []);
        }
        $noindex = $data["seo_noindex"] ?? false;
        if (!is_bool($noindex)) {
            throw new ValidationException([
                "seo_noindex" => "SEO noindex must be a boolean.",
            ]);
        }
        return [
            "seo_title" => self::text($data, "seo_title", 70),
            "seo_description" => self::text($data, "seo_description", 170),
            "seo_noindex" => (int) $noindex,
            "title" => $title,
            "slug" => $slug,
            "excerpt" => self::text($data, "excerpt", 1000),
            "content" => Validator::field(
                "content",
                fn() => self::blocks($data["content"] ?? []),
            ),
            "details" => $clean,
            "status" => $status,
            "publish_at" => $publish,
            "featured_media_id" => $featured,
        ];
    }
}
