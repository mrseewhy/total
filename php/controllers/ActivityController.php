<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../models/Activity.php";
require_once __DIR__ . "/../config/ContentModules.php";
final class ActivityController
{
    public function index(): void
    {
        $actor = requireStaff();
        if (!$actor) {
            return;
        }
        $page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
        $module = $_GET["module"] ?? "";
        if (
            $page === false ||
            $page < 1 ||
            !is_string($module) ||
            !in_array(
                $module,
                [
                    "",
                    "seo",
                    ...array_keys(ContentModules::all()),
                    "media",
                    "users",
                    "profile",
                    "auth",
                    "contacts",
                ],
                true,
            )
        ) {
            throw new InvalidArgumentException("Invalid activity filters.");
        }
        $db = require __DIR__ . "/../db.php";
        jsonResponse([
            "success" => true,
            ...(new Activity($db))->paginate(
                $page,
                $module,
                $actor["role"] === "Admin",
            ),
        ]);
    }
}
