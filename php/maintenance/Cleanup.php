<?php
declare(strict_types=1);
require_once __DIR__ . "/../middleware/rateLimit.php";
final class MaintenanceCleanup
{
    public static function config(): array
    {
        $config = require __DIR__ . "/../maintenance.config.php";
        foreach (
            [
                "activity_days" => [0, 3650],
                "rate_limit_days" => [2, 3650],
                "batch_size" => [1, 1000],
                "max_rows_per_table" => [1, 100000],
            ]
            as $key => [$minimum, $maximum]
        ) {
            if (
                !is_int($config[$key] ?? null) ||
                $config[$key] < $minimum ||
                $config[$key] > $maximum
            ) {
                throw new RuntimeException(
                    "Invalid maintenance retention/batch configuration: " .
                        $key,
                );
            }
        }
        if (
            $config["rate_limit_days"] * 86400 <=
            RATE_LIMIT_MAX_WINDOW_SECONDS
        ) {
            throw new RuntimeException(
                "Limiter retention must exceed the maximum supported request window.",
            );
        }
        return $config;
    }
    public function __construct(private PDO $db) {}
    public function run(array $config, bool $apply, array $only): array
    {
        $now = time();
        $rules = [
            "resets" => ["password_resets", "user_id", "expires_at <= ?", $now],
            "two-factor" => [
                "two_factor_challenges",
                "id",
                "expires_at <= ?",
                $now,
            ],
            "rate-limits" => [
                "rate_limits",
                "identifier",
                "window_start <= ?",
                $now - $config["rate_limit_days"] * 86400,
            ],
        ];
        if ($config["activity_days"] > 0) {
            $rules["activity"] = [
                "activity_log",
                "id",
                "created_at < ?",
                gmdate("Y-m-d H:i:s", $now - $config["activity_days"] * 86400),
            ];
        }
        $result = [];
        foreach ($only as $name) {
            if ($name === "activity" && $config["activity_days"] === 0) {
                $result[$name] = [
                    "eligible" => 0,
                    "deleted" => 0,
                    "remaining" => 0,
                    "disabled" => true,
                ];
                continue;
            }
            [$table, $key, $where, $cutoff] = $rules[$name];
            $count = $this->db->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$where}",
            );
            $count->execute([$cutoff]);
            $eligible = (int) $count->fetchColumn();
            $deleted = 0;
            if ($apply) {
                while ($deleted < $config["max_rows_per_table"]) {
                    $batch = min(
                        $config["batch_size"],
                        $config["max_rows_per_table"] - $deleted,
                    );
                    $this->db->beginTransaction();
                    try {
                        $query = $this->db->prepare(
                            "SELECT {$key} FROM {$table} WHERE {$where} ORDER BY {$key} LIMIT {$batch}",
                        );
                        $query->execute([$cutoff]);
                        $ids = $query->fetchAll(PDO::FETCH_COLUMN);
                        if (!$ids) {
                            $this->db->commit();
                            break;
                        }
                        // Recheck the timestamp so a renewed token/limiter cannot be removed.
                        $query = $this->db->prepare(
                            "DELETE FROM {$table} WHERE {$where} AND {$key} IN (" .
                                implode(",", array_fill(0, count($ids), "?")) .
                                ")",
                        );
                        $query->execute([$cutoff, ...$ids]);
                        $removed = $query->rowCount();
                        $this->db->commit();
                        $deleted += $removed;
                        if ($removed === 0) {
                            break;
                        }
                    } catch (Throwable $e) {
                        if ($this->db->inTransaction()) {
                            $this->db->rollBack();
                        }
                        throw $e;
                    }
                }
            }
            $count->execute([$cutoff]);
            $result[$name] = [
                "eligible" => $eligible,
                "deleted" => $deleted,
                "remaining" => (int) $count->fetchColumn(),
                "disabled" => false,
            ];
        }
        return [
            "dry_run" => !$apply,
            "as_of" => gmdate("c", $now),
            "retention" => [
                "activity_days" => $config["activity_days"],
                "rate_limit_days" => $config["rate_limit_days"],
            ],
            "tables" => $result,
        ];
    }
}
