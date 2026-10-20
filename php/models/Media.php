<?php
declare(strict_types=1);
require_once __DIR__ . "/../database/crud.php";
final class Media
{
    public function __construct(private PDO $db) {}
    public static function directory(): string
    {
        $config = require __DIR__ . "/../paths.config.php";
        return rtrim($config["public_directory"], "/") . "/uploads";
    }
    public function decode(array $row): array
    {
        foreach (
            ["id", "size_bytes", "width", "height", "uploaded_by"]
            as $key
        ) {
            $row[$key] = (int) $row[$key];
        }
        $row["url"] = "/uploads/" . $row["filename"];
        $stmt = $this->db->prepare(
            "SELECT content_kind,content_id FROM content_media WHERE media_id=?",
        );
        $stmt->execute([$row["id"]]);
        $row["used_by"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }
    public function find(int $id): ?array
    {
        $row = readRecord(
            $this->db,
            "media",
            [
                "id",
                "filename",
                "original_name",
                "mime_type",
                "size_bytes",
                "width",
                "height",
                "title",
                "alt_text",
                "caption",
                "uploaded_by",
                "created_at",
            ],
            ["id" => $id],
        );
        return $row ? $this->decode($row) : null;
    }
    public function paginate(
        int $page,
        string $search,
        bool $unused = false,
    ): array {
        $where =
            "(title LIKE ? OR original_name LIKE ?)" .
            ($unused
                ? " AND NOT EXISTS (SELECT 1 FROM content_media WHERE content_media.media_id = media.id)"
                : "");
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM media WHERE {$where}");
        $stmt->execute(["%" . $search . "%", "%" . $search . "%"]);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / 24));
        $page = min($page, $pages);
        $offset = ($page - 1) * 24;
        $stmt = $this->db->prepare(
            "SELECT * FROM media WHERE {$where} ORDER BY id DESC LIMIT 24 OFFSET {$offset}",
        );
        $stmt->execute(["%" . $search . "%", "%" . $search . "%"]);
        return [
            "data" => array_map(
                $this->decode(...),
                $stmt->fetchAll(PDO::FETCH_ASSOC),
            ),
            "pagination" => [
                "page" => $page,
                "pages" => $pages,
                "total" => $total,
                "perPage" => 24,
            ],
        ];
    }
    public function upload(array $file, array $info, int $actor): array
    {
        $filename = bin2hex(random_bytes(16)) . "." . $info["extension"];
        $path = self::directory() . "/" . $filename;
        if (!move_uploaded_file($file["tmp_name"], $path)) {
            throw new RuntimeException("Upload directory is not writable.");
        }
        try {
            $name = substr(
                basename(str_replace("\\", "/", $file["name"])),
                0,
                250,
            );
            $name = preg_match("//u", $name)
                ? $name
                : "image." . $info["extension"];
            createRecord($this->db, "media", [
                "filename" => $filename,
                "original_name" => $name,
                "mime_type" => $info["mime"],
                "size_bytes" => filesize($path),
                "width" => $info["width"],
                "height" => $info["height"],
                "title" =>
                    substr(
                        preg_replace(
                            "/[^a-zA-Z0-9 ._-]/",
                            "",
                            pathinfo($name, PATHINFO_FILENAME),
                        ),
                        0,
                        180,
                    ) ?:
                    "Image",
                "alt_text" => "",
                "caption" => "",
                "uploaded_by" => $actor,
                "created_at" => gmdate("Y-m-d H:i:s"),
            ]);
            $stmt = $this->db->prepare("SELECT id FROM media WHERE filename=?");
            $stmt->execute([$filename]);
            return $this->find((int) $stmt->fetchColumn());
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }
    public function update(int $id, array $data): ?array
    {
        if (!$this->find($id)) {
            return null;
        }
        updateRecords($this->db, "media", $data, ["id" => $id]);
        return $this->find($id);
    }
    public function delete(array $ids): void
    {
        $moved = [];
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE content_lock SET id=id WHERE id=1");
            foreach ($ids as $id) {
                $media = $this->find($id);
                if (!$media) {
                    throw new OutOfBoundsException("Media not found.");
                }
                if ($media["used_by"]) {
                    throw new InvalidArgumentException(
                        "An image is in use. Remove it from its content before deleting.",
                    );
                }
            }
            foreach ($ids as $id) {
                $media = $this->find($id);
                $path = self::directory() . "/" . $media["filename"];
                $trash = __DIR__ . "/../storage/" . bin2hex(random_bytes(16));
                if (is_file($path)) {
                    if (!rename($path, $trash)) {
                        throw new RuntimeException(
                            "Could not remove image file.",
                        );
                    }
                    $moved[] = [$path, $trash];
                }
                deleteRecords($this->db, "media", ["id" => $id]);
            }
            $this->db->commit();
            foreach ($moved as [$path, $trash]) {
                if (!unlink($trash)) {
                    error_log("Could not purge quarantined media file.");
                }
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            foreach ($moved as [$path, $trash]) {
                if (is_file($trash)) {
                    rename($trash, $path);
                }
            }
            throw $e;
        }
    }
}
