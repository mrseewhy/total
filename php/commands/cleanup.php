<?php
declare(strict_types=1);
require_once __DIR__ . "/../console/cli.php";
cliOnly();
require_once __DIR__ . "/../maintenance/Cleanup.php";
require_once __DIR__ . "/../maintenance/Mode.php";
if (in_array("--help", $argv, true)) {
    echo "Usage: php php/commands/cleanup.php [--dry-run|--apply] [--only=resets,two-factor,rate-limits,activity] [--json]\nDefaults to dry run. Retention/batch settings: php/maintenance.config.local.php. No content, users, uploads or backups are deleted.\n";
    exit();
}
$locks = [];
$cleanupLock = null;
try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, "--")) {
            throw new RuntimeException("Unknown argument. Use --help.");
        }
        [$key, $value] = array_pad(explode("=", substr($arg, 2), 2), 2, true);
        if (
            !in_array($key, ["dry-run", "apply", "only", "json"], true) ||
            isset($options[$key])
        ) {
            throw new RuntimeException("Unknown or duplicate option.");
        }
        if (
            $key === "only"
                ? !is_string($value) || $value === ""
                : $value !== true
        ) {
            throw new RuntimeException("Invalid option value.");
        }
        $options[$key] = $value;
    }
    if (isset($options["apply"], $options["dry-run"])) {
        throw new RuntimeException("Use --dry-run or --apply, not both.");
    }
    $only = isset($options["only"])
        ? explode(",", $options["only"])
        : ["resets", "two-factor", "rate-limits", "activity"];
    if (
        count(array_unique($only)) !== count($only) ||
        array_diff($only, ["resets", "two-factor", "rate-limits", "activity"])
    ) {
        throw new RuntimeException(
            "Choose only resets, two-factor, rate-limits and/or activity.",
        );
    }
    $config = MaintenanceCleanup::config();
    $locks = MaintenanceMode::commandLock(false, false);
    $cleanupLock = fopen(MaintenanceMode::storage() . "/.cleanup.lock", "c");
    if (!$cleanupLock || !flock($cleanupLock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException("Another cleanup is running.");
    }
    $db = (static function (): PDO {
        return require __DIR__ . "/../db.php";
    })();
    $report = (new MaintenanceCleanup($db))->run(
        $config,
        isset($options["apply"]),
        $only,
    );
    if (isset($options["json"])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) .
            PHP_EOL;
    } else {
        echo ($report["dry_run"]
            ? "DRY RUN — no records deleted."
            : "Cleanup applied.") . PHP_EOL;
        foreach ($report["tables"] as $name => $row) {
            echo $name .
                ": " .
                $row["eligible"] .
                " eligible; " .
                $row["deleted"] .
                " deleted; " .
                $row["remaining"] .
                " remaining" .
                ($row["disabled"] ? " (retention disabled)" : "") .
                PHP_EOL;
        }
        if (!$report["dry_run"]) {
            echo "Repeat if eligible records remain beyond the per-run limit." .
                PHP_EOL;
        }
    }
} catch (Throwable $e) {
    fwrite(
        STDERR,
        ($e instanceof PDOException
            ? "Cleanup database operation failed. Check configuration, migrations and permissions."
            : $e->getMessage()) . PHP_EOL,
    );
    exit(1);
} finally {
    if (is_resource($cleanupLock)) {
        flock($cleanupLock, LOCK_UN);
        fclose($cleanupLock);
    }
    MaintenanceMode::release($locks);
}
