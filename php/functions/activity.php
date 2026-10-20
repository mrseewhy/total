<?php
declare(strict_types=1);
/** Operational feed: explicitly pass a summary, never request bodies or secrets. */
function recordActivity(
    PDO $db,
    array $actor,
    string $module,
    string $action,
    string|int $target,
    string $summary,
): void {
    try {
        $stmt = $db->prepare(
            "INSERT INTO activity_log (actor_id,actor_name,module,action,target_id,summary,created_at) VALUES (?,?,?,?,?,?,?)",
        );
        $stmt->execute([
            (int) $actor["id"],
            substr($actor["name"], 0, 100),
            $module,
            $action,
            substr((string) $target, 0, 200),
            $summary,
            gmdate("Y-m-d H:i:s"),
        ]);
    } catch (Throwable $e) {
        // A feed outage must not turn an already-saved operation into a failed response.
        error_log("Activity recording failed: " . $e->getMessage());
    }
}
