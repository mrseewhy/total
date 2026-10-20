<?php
declare(strict_types=1);
require_once __DIR__ . "/ContentValidation.php";
final class SeoMetadataValidation
{
    private static function bool(array $data, string $key): int
    {
        if (!is_bool($data[$key] ?? null)) {
            throw new ValidationException([$key => "Invalid {$key}."]);
        }
        return (int) $data[$key];
    }
    private static function image(array $data, string $key): string
    {
        $url = ContentValidation::text($data, $key, 2000);
        if (
            $url !== "" &&
            (!filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array(
                    strtolower(parse_url($url, PHP_URL_SCHEME) ?? ""),
                    ["https", "http"],
                    true,
                ))
        ) {
            throw new ValidationException([
                $key => "Use a full HTTP or HTTPS image URL.",
            ]);
        }
        return $url;
    }
    public static function page(array $data): array
    {
        return [
            "title" => ContentValidation::text($data, "title", 70),
            "description" => ContentValidation::text($data, "description", 170),
            "image" => self::image($data, "image"),
            "noindex" => self::bool($data, "noindex"),
        ];
    }
    public static function settings(array $data): array
    {
        $language = ContentValidation::text($data, "language", 20, true);
        $locale = ContentValidation::text($data, "locale", 20, true);
        if (
            !preg_match('/^[a-z]{2,3}(?:-[a-zA-Z]{2,8})?$/D', $language) ||
            !preg_match('/^[a-z]{2,3}_[A-Z]{2}$/D', $locale)
        ) {
            throw new InvalidArgumentException(
                "Use a language such as en and locale such as en_NG.",
            );
        }
        return [
            "site_name" => ContentValidation::text(
                $data,
                "site_name",
                100,
                true,
            ),
            "description" => ContentValidation::text($data, "description", 170),
            "default_image" => self::image($data, "default_image"),
            "language" => $language,
            "locale" => $locale,
            "noindex" => self::bool($data, "noindex"),
        ];
    }
}
