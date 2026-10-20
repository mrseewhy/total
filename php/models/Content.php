<?php
declare(strict_types=1);
require_once __DIR__ . "/../database/crud.php";
require_once __DIR__ . "/../config/ContentModules.php";
class Content
{
    private string $table;
    public function __construct(protected PDO $db, protected string $kind)
    {
        ContentModules::get($kind);
        $this->table = crudIdentifier($db, $kind);
    }
    public function decode(array $row): array
    {
        $row["seo_noindex"] = (bool) ($row["seo_noindex"] ?? false);
        $row["id"] = (int) $row["id"];
        $row["featured_media_id"] =
            $row["featured_media_id"] === null
                ? null
                : (int) $row["featured_media_id"];
        $row["content"] = json_decode(
            $row["content"],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $row["details"] = json_decode(
            $row["details"],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $row["effective_status"] =
            $row["status"] === "scheduled" &&
            $row["publish_at"] <= gmdate("Y-m-d H:i:s")
                ? "published"
                : $row["status"];
        return $row;
    }
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decode($row) : null;
    }
    public function publicBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE slug=? AND status IN ('published','scheduled') AND publish_at<=?",
        );
        $stmt->execute([$slug, gmdate("Y-m-d H:i:s")]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decode($row) : null;
    }
    public function paginate(
        int $page,
        string $search = "",
        string $status = "",
    ): array {
        $where = " WHERE title LIKE ?";
        $params = ["%" . $search . "%"];
        if ($status !== "") {
            $where .=
                " AND (CASE WHEN status='scheduled' AND publish_at<=? THEN 'published' ELSE status END)=?";
            $params[] = gmdate("Y-m-d H:i:s");
            $params[] = $status;
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}{$where}",
        );
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $offset = ($page - 1) * 20;
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}{$where} ORDER BY id DESC LIMIT 20 OFFSET {$offset}",
        );
        $stmt->execute($params);
        return [
            "data" => array_map(
                $this->decode(...),
                $stmt->fetchAll(PDO::FETCH_ASSOC),
            ),
            "pagination" => [
                "page" => $page,
                "pages" => $pages,
                "total" => $total,
                "perPage" => 20,
            ],
        ];
    }
    public function save(array $data, int $author, ?int $id): array
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE content_lock SET id=id WHERE id=1");
            $old = $id ? $this->find($id) : null;
            if ($id && !$old) {
                throw new OutOfBoundsException("Content not found.");
            }
            if (
                $old &&
                $old["effective_status"] === "published" &&
                $data["status"] === "published"
            ) {
                $data["publish_at"] = $old["publish_at"];
            }
            $media = $data["featured_media_id"]
                ? [$data["featured_media_id"]]
                : [];
            array_push($media, ...$data["details"]["gallery_ids"] ?? []);
            $json = json_encode(
                $data["content"],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
            preg_match_all(
                "#/uploads/([a-f0-9]{32}\.(?:jpg|png|webp|gif))#",
                $json,
                $matches,
            );
            foreach (array_unique($matches[1]) as $filename) {
                $row = readRecord(
                    $this->db,
                    "media",
                    ["id"],
                    ["filename" => $filename],
                );
                if (!$row) {
                    throw new InvalidArgumentException(
                        "An editor image no longer exists.",
                    );
                }
                $media[] = (int) $row["id"];
            }
            $media = array_values(array_unique($media));
            foreach ($media as $mediaId) {
                if (
                    !readRecord($this->db, "media", ["id"], ["id" => $mediaId])
                ) {
                    throw new InvalidArgumentException(
                        "Selected media no longer exists.",
                    );
                }
            }
            $data["content"] = $json;
            $data["details"] = json_encode(
                $data["details"],
                JSON_THROW_ON_ERROR,
            );
            $data["updated_at"] = gmdate("Y-m-d H:i:s");
            if ($id) {
                updateRecords($this->db, $this->kind, $data, ["id" => $id]);
            } else {
                $data["author_id"] = $author;
                $data["created_at"] = $data["updated_at"];
                createRecord($this->db, $this->kind, $data);
                $stmt = $this->db->prepare(
                    "SELECT id FROM {$this->table} WHERE slug=?",
                );
                $stmt->execute([$data["slug"]]);
                $id = (int) $stmt->fetchColumn();
            }
            deleteRecords($this->db, "content_media", [
                "content_kind" => $this->kind,
                "content_id" => $id,
            ]);
            foreach ($media as $mediaId) {
                createRecord($this->db, "content_media", [
                    "content_kind" => $this->kind,
                    "content_id" => $id,
                    "media_id" => $mediaId,
                ]);
            }
            if ($old && $old["slug"] !== $data["slug"]) {
                deleteRecords($this->db, "seo_redirects", [
                    "kind" => $this->kind,
                    "old_slug" => $data["slug"],
                ]);
                updateRecords(
                    $this->db,
                    "seo_redirects",
                    ["new_slug" => $data["slug"]],
                    ["kind" => $this->kind, "new_slug" => $old["slug"]],
                );
                $redirect = $this->db->prepare(
                    "SELECT old_slug FROM seo_redirects WHERE kind=? AND old_slug=?",
                );
                $redirect->execute([$this->kind, $old["slug"]]);
                if ($redirect->fetchColumn()) {
                    updateRecords(
                        $this->db,
                        "seo_redirects",
                        ["new_slug" => $data["slug"]],
                        ["kind" => $this->kind, "old_slug" => $old["slug"]],
                    );
                } else {
                    createRecord($this->db, "seo_redirects", [
                        "kind" => $this->kind,
                        "old_slug" => $old["slug"],
                        "new_slug" => $data["slug"],
                    ]);
                }
            }
            $this->db->commit();
            return $this->find($id);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    public function delete(array $ids): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE content_lock SET id=id WHERE id=1");
            foreach ($ids as $id) {
                $item = $this->find($id);
                if (!$item) {
                    throw new OutOfBoundsException("Content not found.");
                }
                deleteRecords($this->db, "content_media", [
                    "content_kind" => $this->kind,
                    "content_id" => $id,
                ]);
                deleteRecords($this->db, "seo_redirects", [
                    "kind" => $this->kind,
                    "new_slug" => $item["slug"],
                ]);
                deleteRecords($this->db, $this->kind, ["id" => $id]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
