<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../models/Media.php";
require_once __DIR__ . "/../validation/MediaValidation.php";
final class MediaController
{
    private function id(string $id): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException("Invalid ID.");
        }
        return $value;
    }
    private function write(): ?array
    {
        $actor = requireStaff();
        return $actor && verifyCsrf() ? $actor : null;
    }
    private function limit(PDO $db, int $actor): bool
    {
        if (checkRateLimit($db, (string) $actor, "media-write", 100, 600)) {
            return true;
        }
        header("Retry-After: 600");
        jsonResponse(
            [
                "success" => false,
                "message" => "Too many media changes. Try again later.",
            ],
            429,
        );
        return false;
    }
    public function index(): void
    {
        if (!requireStaff()) {
            return;
        }
        $page = $this->id((string) ($_GET["page"] ?? 1));
        $search = $_GET["search"] ?? "";
        if (!is_string($search) || strlen($search) > 200) {
            throw new InvalidArgumentException("Invalid search.");
        }
        $db = require __DIR__ . "/../db.php";
        jsonResponse([
            "success" => true,
            ...(new Media($db))->paginate(
                $page,
                $search,
                ($_GET["unused"] ?? "0") === "1",
            ),
        ]);
    }
    public function show(string $id): void
    {
        if (!requireStaff()) {
            return;
        }
        $db = require __DIR__ . "/../db.php";
        $media = (new Media($db))->find($this->id($id));
        jsonResponse(
            $media
                ? ["success" => true, "data" => $media]
                : ["success" => false, "message" => "Media not found."],
            $media ? 200 : 404,
        );
    }
    public function upload(): void
    {
        $actor = $this->write();
        if (!$actor) {
            return;
        }
        $db = require __DIR__ . "/../db.php";
        if (!$this->limit($db, (int) $actor["id"])) {
            return;
        }
        $file = $_FILES["file"] ?? null;
        if (!is_array($file)) {
            throw new InvalidArgumentException("Choose an image to upload.");
        }
        $info = MediaValidation::upload($file);
        $media = (new Media($db))->upload($file, $info, (int) $actor["id"]);
        recordActivity(
            $db,
            $actor,
            "media",
            "uploaded",
            $media["id"],
            "Uploaded image " . $media["title"] . ".",
        );
        jsonResponse(["success" => true, "data" => $media], 201);
    }

    public function update(string $id): void
    {
        if (!($actor = $this->write())) {
            return;
        }
        $data = MediaValidation::details(jsonRequest());
        $db = require __DIR__ . "/../db.php";
        if (!$this->limit($db, (int) $actor["id"])) {
            return;
        }
        $media = (new Media($db))->update($this->id($id), $data);
        if ($media) {
            recordActivity(
                $db,
                $actor,
                "media",
                "updated",
                $id,
                "Updated image details.",
            );
        }
        jsonResponse(
            $media
                ? ["success" => true, "data" => $media]
                : ["success" => false, "message" => "Media not found."],
            $media ? 200 : 404,
        );
    }
    public function delete(?string $id = null): void
    {
        if (!($actor = $this->write())) {
            return;
        }
        $ids = $id
            ? [$this->id($id)]
            : ContentValidation::ids(jsonRequest()["ids"] ?? null);
        if (!$ids) {
            throw new InvalidArgumentException("Select images to delete.");
        }
        $db = require __DIR__ . "/../db.php";
        if (!$this->limit($db, (int) $actor["id"])) {
            return;
        }
        try {
            (new Media($db))->delete($ids);
            recordActivity(
                $db,
                $actor,
                "media",
                "deleted",
                implode(",", $ids),
                "Deleted " . count($ids) . " image(s).",
            );
            jsonResponse(["success" => true, "message" => "Media deleted."]);
        } catch (OutOfBoundsException $e) {
            jsonResponse(
                ["success" => false, "message" => $e->getMessage()],
                404,
            );
        }
    }
}
