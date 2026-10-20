<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../models/SeoMetadata.php";
require_once __DIR__ . "/../validation/SeoMetadataValidation.php";
final class MetadataController
{
    public function index(): void
    {
        if (!requireStaff()) {
            return;
        }
        $db = require __DIR__ . "/../db.php";
        $model = new SeoMetadata($db);
        $config = require __DIR__ . "/../seo.config.php";
        $settings = $model->settings() ?? [
            "site_name" => $config["name"],
            "description" => $config["description"],
            "default_image" => $config["default_image"],
            "language" => $config["language"],
            "locale" => $config["locale"],
            "noindex" => false,
        ];
        $pages = [];
        foreach (SeoMetadata::pages() as $path => $label) {
            $pages[] = [
                "path" => $path,
                "label" => $label,
                "metadata" => $model->page($path) ?? [
                    "title" => "",
                    "description" => "",
                    "image" => "",
                    "noindex" => false,
                ],
            ];
        }
        jsonResponse([
            "success" => true,
            "settings" => $settings,
            "pages" => $pages,
        ]);
    }
    public function save(): void
    {
        $actor = requireStaff();
        if (!$actor || !verifyCsrf()) {
            return;
        }
        $input = jsonRequest();
        $scope = $input["scope"] ?? null;
        if ($scope === "settings") {
            $data = SeoMetadataValidation::settings($input);
            $table = "seo_settings";
            $recordKey = ["id" => 1];
            $target = "defaults";
        } elseif ($scope === "page") {
            $path = $input["path"] ?? null;
            if (
                !is_string($path) ||
                !array_key_exists($path, SeoMetadata::pages())
            ) {
                throw new InvalidArgumentException("Choose a registered page.");
            }
            $data = SeoMetadataValidation::page($input);
            $table = "page_metadata";
            $recordKey = ["path" => $path];
            $target = $path;
        } else {
            throw new InvalidArgumentException("Invalid metadata scope.");
        }
        $db = require __DIR__ . "/../db.php";
        if (
            !checkRateLimit(
                $db,
                (string) $actor["id"],
                "metadata-write",
                60,
                600,
            )
        ) {
            header("Retry-After: 600");
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Too many changes. Try again later.",
                ],
                429,
            );
            return;
        }
        (new SeoMetadata($db))->save($table, $recordKey, $data);
        recordActivity(
            $db,
            $actor,
            "seo",
            "updated",
            $target,
            "Updated SEO metadata for " . $target . ".",
        );
        jsonResponse(["success" => true, "message" => "SEO metadata saved."]);
    }
}
