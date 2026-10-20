<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../models/Content.php";
require_once __DIR__ . "/../validation/ContentValidation.php";
class ContentController
{
    public function __construct(private string $kind) {}
    private function model(PDO $db): Content
    {
        return new Content($db, $this->kind);
    }
    private function number(string $id): int
    {
        $n = filter_var($id, FILTER_VALIDATE_INT);
        if ($n === false || $n < 1) {
            throw new InvalidArgumentException("Invalid ID.");
        }
        return $n;
    }
    public function index(): void
    {
        if (!requireStaff()) {
            return;
        }
        $page = $this->number((string) ($_GET["page"] ?? 1));
        $q = $_GET["search"] ?? "";
        $status = $_GET["status"] ?? "";
        if (
            !is_string($q) ||
            strlen($q) > 200 ||
            !is_string($status) ||
            !in_array($status, ["", "draft", "scheduled", "published"], true)
        ) {
            throw new InvalidArgumentException("Invalid filters.");
        }
        $db = require __DIR__ . "/../db.php";
        jsonResponse([
            "success" => true,
            ...$this->model($db)->paginate($page, $q, $status),
        ]);
    }
    public function show(string $id): void
    {
        if (!requireStaff()) {
            return;
        }
        $db = require __DIR__ . "/../db.php";
        $row = $this->model($db)->find($this->number($id));
        jsonResponse(
            $row
                ? ["success" => true, "data" => $row]
                : ["success" => false, "message" => "Content not found."],
            $row ? 200 : 404,
        );
    }
    public function publicShow(string $slug): void
    {
        $db = require __DIR__ . "/../db.php";
        $row = $this->model($db)->publicBySlug($slug);
        jsonResponse(
            $row
                ? ["success" => true, "data" => $row]
                : ["success" => false, "message" => "Content not found."],
            $row ? 200 : 404,
        );
    }
    public function save(?string $id = null): void
    {
        $actor = requireStaff();
        if (!$actor || !verifyCsrf()) {
            return;
        }
        $data = ContentValidation::validate(jsonRequest(), $this->kind);
        $db = require __DIR__ . "/../db.php";
        if (
            !checkRateLimit(
                $db,
                (string) $actor["id"],
                "content-write",
                120,
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
        try {
            $row = $this->model($db)->save(
                $data,
                (int) $actor["id"],
                $id ? $this->number($id) : null,
            );
            $module = $this->kind;
            recordActivity(
                $db,
                $actor,
                $module,
                $id ? "updated" : "created",
                $row["id"],
                ($id ? "Updated" : "Created") .
                    " " .
                    $row["title"] .
                    " (" .
                    $row["status"] .
                    ").",
            );
            jsonResponse(
                [
                    "success" => true,
                    "data" => $row,
                    "message" => "Content saved.",
                ],
                $id ? 200 : 201,
            );
        } catch (OutOfBoundsException $e) {
            jsonResponse(
                ["success" => false, "message" => $e->getMessage()],
                404,
            );
        } catch (PDOException $e) {
            if (isUniqueViolation($db, $e)) {
                jsonResponse(
                    [
                        "success" => false,
                        "message" => "This slug is already in use.",
                        "errors" => ["slug" => "This slug is already in use."],
                    ],
                    409,
                );
                return;
            }
            throw $e;
        }
    }
    public function delete(?string $id = null): void
    {
        $actor = requireStaff();
        if (!$actor || !verifyCsrf()) {
            return;
        }
        $ids = $id
            ? [$this->number($id)]
            : ContentValidation::ids(jsonRequest()["ids"] ?? null);
        if (!$ids) {
            throw new InvalidArgumentException("Select content to delete.");
        }
        $db = require __DIR__ . "/../db.php";
        if (
            !checkRateLimit(
                $db,
                (string) $actor["id"],
                "content-delete",
                60,
                600,
            )
        ) {
            header("Retry-After: 600");
            jsonResponse(
                ["success" => false, "message" => "Too many changes."],
                429,
            );
            return;
        }
        try {
            $this->model($db)->delete($ids);
            $module = $this->kind;
            recordActivity(
                $db,
                $actor,
                $module,
                "deleted",
                implode(",", $ids),
                "Deleted " . count($ids) . " content item(s).",
            );
            jsonResponse(["success" => true, "message" => "Content deleted."]);
        } catch (OutOfBoundsException $e) {
            jsonResponse(
                ["success" => false, "message" => $e->getMessage()],
                404,
            );
        }
    }
}
