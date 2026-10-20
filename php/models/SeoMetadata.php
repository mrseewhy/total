<?php
declare(strict_types=1);
final class SeoMetadata
{
    public function __construct(private PDO $db) {}
    public static function routes(): array
    {
        $paths = require __DIR__ . "/../paths.config.php";
        $file = rtrim($paths["public_directory"], "/") . "/route-manifest.json";
        if (!is_file($file)) {
            throw new RuntimeException(
                "Public route manifest missing. Run npm run dev or npm run build and deploy route-manifest.json.",
            );
        }
        $manifest = json_decode(
            file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (
            ($manifest["version"] ?? null) !== 1 ||
            !is_array($manifest["pages"] ?? null)
        ) {
            throw new RuntimeException("Invalid public route manifest.");
        }
        $routes = [];
        foreach ($manifest["pages"] as $path => $page) {
            if (
                !is_string($path) ||
                !preg_match(
                    '#^/(?:[a-zA-Z0-9._~-]+(?:/[a-zA-Z0-9._~-]+)*)?$#D',
                    $path,
                ) ||
                preg_match(
                    '#^/(site-admin|admin|dashboard|api)(/|$)#i',
                    $path,
                ) ||
                !is_array($page) ||
                !is_string($page["label"] ?? null)
            ) {
                throw new RuntimeException("Invalid public route entry.");
            }
            foreach (["title", "description", "heading", "schema"] as $key) {
                if (isset($page[$key]) && !is_string($page[$key])) {
                    throw new RuntimeException(
                        "Invalid route metadata default.",
                    );
                }
            }
            if (isset($page["noindex"]) && !is_bool($page["noindex"])) {
                throw new RuntimeException("Invalid route indexing flag.");
            }
            $routes[$path] = $page;
        }
        return $routes;
    }
    public static function pages(): array
    {
        return array_map(fn($page) => $page["label"], self::routes());
    }
    public function settings(): ?array
    {
        $row = $this->db
            ->query("SELECT * FROM seo_settings WHERE id=1")
            ->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row["noindex"] = (bool) $row["noindex"];
        return $row;
    }
    public function page(string $path): ?array
    {
        $q = $this->db->prepare("SELECT * FROM page_metadata WHERE path=?");
        $q->execute([$path]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row["noindex"] = (bool) $row["noindex"];
        return $row;
    }
    public function save(string $table, array $key, array $data): void
    {
        if (!in_array($table, ["seo_settings", "page_metadata"], true)) {
            throw new InvalidArgumentException("Invalid metadata table.");
        }
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE content_lock SET id=id WHERE id=1");
            $column = array_key_first($key);
            $q = $this->db->prepare(
                "SELECT {$column} FROM {$table} WHERE {$column}=?",
            );
            $q->execute(array_values($key));
            $data["updated_at"] = gmdate("Y-m-d H:i:s");
            if ($q->fetchColumn() !== false) {
                $set = implode(
                    ",",
                    array_map(fn($c) => $c . "=?", array_keys($data)),
                );
                $q = $this->db->prepare(
                    "UPDATE {$table} SET {$set} WHERE {$column}=?",
                );
                $q->execute([...array_values($data), ...array_values($key)]);
            } else {
                $data = [...$key, ...$data];
                $columns = implode(",", array_keys($data));
                $values = implode(",", array_fill(0, count($data), "?"));
                $q = $this->db->prepare(
                    "INSERT INTO {$table} ({$columns}) VALUES ({$values})",
                );
                $q->execute(array_values($data));
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
