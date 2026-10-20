<?php
declare(strict_types=1);
require_once __DIR__ . "/backup.php";
require_once __DIR__ . "/../maintenance/Mode.php";
function backupCommand(array $argv, bool $restore): void
{
    cliOnly();
    $usage = $restore
        ? "php php/commands/restore.php <backup.zip> [--verify] [--offline --confirm=RESTORE] [--with-config] [--directory=/private/backups] [--mysql=/path/mysql] [--mysqldump=/path/mysqldump]"
        : "php php/commands/backup.php --offline [--directory=/private/backups] [--mysqldump=/path/mysqldump]";
    if (in_array("--help", $argv, true)) {
        echo $usage .
            PHP_EOL .
            "Enable maintenance.php down and stop external writes/migrations before --offline. Archives contain unencrypted personal data and credentials; keep private and copy off-host." .
            PHP_EOL;
        return;
    }
    $previous = umask(0077);
    $lock = null;
    $maintenanceLocks = [];
    try {
        $options = [];
        $archive = null;
        foreach (array_slice($argv, 1) as $arg) {
            if (str_starts_with($arg, "--")) {
                [$key, $value] = array_pad(
                    explode("=", substr($arg, 2), 2),
                    2,
                    true,
                );
                $allowed = $restore
                    ? [
                        "verify",
                        "offline",
                        "confirm",
                        "with-config",
                        "directory",
                        "mysql",
                        "mysqldump",
                    ]
                    : ["offline", "directory", "mysqldump"];
                if (!in_array($key, $allowed, true) || isset($options[$key])) {
                    throw new RuntimeException(
                        "Invalid or duplicate option. Usage: " . $usage,
                    );
                }
                $flags = ["offline", "verify", "with-config"];
                if (
                    in_array($key, $flags, true)
                        ? $value !== true
                        : !is_string($value) || $value === ""
                ) {
                    throw new RuntimeException("Invalid option value.");
                }
                $options[$key] = $value;
            } elseif ($restore && $archive === null) {
                $archive = $arg;
            } else {
                throw new RuntimeException("Usage: " . $usage);
            }
        }
        if ($restore && (!$archive || !is_file($archive))) {
            throw new RuntimeException("Supply an existing backup ZIP file.");
        }
        $verify = isset($options["verify"]);
        if (
            $verify &&
            (isset($options["with-config"]) || isset($options["confirm"]))
        ) {
            throw new RuntimeException(
                "--verify cannot be combined with restore options.",
            );
        }
        if (!$verify && !isset($options["offline"])) {
            throw new RuntimeException(
                "Run maintenance.php down first, pause external writers, then supply --offline.",
            );
        }
        if ($restore && !$verify && ($options["confirm"] ?? "") !== "RESTORE") {
            throw new RuntimeException(
                "Restore replaces database contents and uploads. Supply --confirm=RESTORE after verifying this trusted archive and taking the site offline.",
            );
        }
        if (!$verify) {
            $maintenanceLocks = MaintenanceMode::commandLock();
        }
        $service = new SiteBackup();
        $directory = $options["directory"] ?? $service->defaultDirectory();
        $storage = dirname(__DIR__) . "/storage";
        if (!is_dir($storage) && !mkdir($storage, 0700)) {
            throw new RuntimeException("Cannot create private storage.");
        }
        $lock = fopen($storage . "/.backup.lock", "c");
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException(
                "Another backup or restore command is running.",
            );
        }
        if ($restore) {
            if (!$verify && isset($options["with-config"])) {
                echo "Restoring configuration too: archived database credentials and paths will replace this server's settings AFTER import. Use only on the same deployment.\n";
            }
            $result = $service->restore(
                $archive,
                $directory,
                $options["mysql"] ?? "mysql",
                $options["mysqldump"] ?? "mysqldump",
                isset($options["with-config"]),
                $verify,
            );
            echo ($verify ? "Verified archive" : "Restored data") .
                ": " .
                count($result["manifest"]["files"]) .
                " files; created " .
                $result["manifest"]["created_at"] .
                PHP_EOL;
            if (!$verify) {
                echo "Run the health check and verify the website before enabling traffic. Existing browser sessions may need to sign in again.\n";
            }
        } else {
            echo "Backup created: " .
                $service->create(
                    $directory,
                    $options["mysqldump"] ?? "mysqldump",
                ) .
                PHP_EOL;
        }
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            ($e instanceof PDOException
                ? "Database operation failed."
                : $e->getMessage()) . PHP_EOL,
        );
        exit(1);
    } finally {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        MaintenanceMode::release($maintenanceLocks);
        umask($previous);
    }
}
