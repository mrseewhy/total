<?php
declare(strict_types=1);
final class ContentModules
{
    public static function all(): array
    {
        static $modules = null;
        if ($modules !== null) {
            return $modules;
        }
        $json = file_get_contents(__DIR__ . "/content-modules.json");
        if (
            !(
                json_decode($json, false, 512, JSON_THROW_ON_ERROR) instanceof
                stdClass
            )
        ) {
            throw new RuntimeException(
                "Content modules must be a JSON object.",
            );
        }
        $modules = self::validate(
            json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );
        return $modules;
    }
    public static function validate(array $raw): array
    {
        $modules = [];
        $paths = [];
        $reserved = [
            "auth",
            "metadata",
            "activity",
            "media",
            "contacts",
            "subscribers",
            "users",
            "public",
            "seo",
            "admin",
            "dashboard",
            "site_admin",
            "content_lock",
            "content_media",
            "migrations",
            "seo_settings",
            "page_metadata",
            "activity_log",
            "rate_limits",
            "password_resets",
            "user_management_lock",
        ];
        foreach ($raw as $id => $data) {
            if (
                !is_string($id) ||
                !preg_match('/^[a-z][a-z0-9_]{0,19}$/D', $id) ||
                in_array($id, $reserved, true) ||
                !is_array($data)
            ) {
                throw new RuntimeException(
                    "Invalid or reserved content module.",
                );
            }
            if (
                array_diff(array_keys($data), [
                    "label",
                    "singular",
                    "path",
                    "icon",
                    "profile",
                    "description",
                    "scheduling",
                    "navigation",
                ])
            ) {
                throw new RuntimeException("Unknown content module field.");
            }
            foreach (["label", "singular", "path"] as $key) {
                if (
                    !is_string($data[$key] ?? null) ||
                    trim($data[$key]) === "" ||
                    strlen($data[$key]) > 100
                ) {
                    throw new RuntimeException(
                        "Invalid content module " . $key . ".",
                    );
                }
            }
            $path = $data["path"];
            if (
                !preg_match('/^[a-z][a-z0-9-]*$/D', $path) ||
                in_array(
                    $path,
                    [
                        "about",
                        "contact",
                        "site-admin",
                        "admin",
                        "dashboard",
                        "api",
                    ],
                    true,
                ) ||
                isset($paths[$path])
            ) {
                throw new RuntimeException(
                    "Invalid, reserved, or duplicate module path.",
                );
            }
            $paths[$path] = true;
            $data += [
                "profile" => "content",
                "icon" => "FileText",
                "description" => "",
                "scheduling" => false,
                "navigation" => true,
            ];
            if (
                !in_array(
                    $data["profile"],
                    ["content", "article", "event", "property", "service"],
                    true,
                ) ||
                !in_array(
                    $data["icon"],
                    [
                        "FileText",
                        "BriefcaseBusiness",
                        "CalendarDays",
                        "House",
                        "MessageSquare",
                        "Folder",
                    ],
                    true,
                ) ||
                !is_string($data["description"]) ||
                strlen($data["description"]) > 1000 ||
                !is_bool($data["scheduling"]) ||
                !is_bool($data["navigation"])
            ) {
                throw new RuntimeException("Invalid module options.");
            }
            $data["label"] = trim($data["label"]);
            $data["singular"] = trim($data["singular"]);
            $modules[$id] = $data;
        }
        return $modules;
    }
    public static function get(string $id): array
    {
        return self::all()[$id] ??
            throw new InvalidArgumentException("Unknown content module.");
    }
    public static function paths(): array
    {
        return array_map(fn($module) => $module["path"], self::all());
    }
}
