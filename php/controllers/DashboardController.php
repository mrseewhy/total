<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../models/SeoMetadata.php";
require_once __DIR__ . "/../config/ContentModules.php";
require_once __DIR__ . "/../database/crud.php";
final class DashboardController
{
    public function index(): void
    {
        $actor = requireStaff();
        if (!$actor) {
            return;
        }
        $db = require __DIR__ . "/../db.php";
        $pages = [];
        foreach (SeoMetadata::pages() as $path => $label) {
            $pages[] = ["path" => $path, "label" => $label];
        }
        $counts = [
            "pages" => count($pages),
            "unread" => (int) $db
                ->query("SELECT COUNT(*) FROM contacts WHERE is_read = FALSE")
                ->fetchColumn(),
        ];
        // Only the owner can inspect account information.
        if ($actor["role"] === "Admin") {
            $counts["users"] = countRecords($db, "users");
        }
        $modules = [];
        foreach (ContentModules::all() as $id => $definition) {
            $modules[] = [
                "id" => $id,
                "label" => $definition["label"],
                "total" => countRecords($db, $id),
            ];
        }
        jsonResponse([
            "success" => true,
            "counts" => $counts,
            "pages" => $pages,
            "modules" => $modules,
        ]);
    }
}
