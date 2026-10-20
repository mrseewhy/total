<?php
declare(strict_types=1);
require_once __DIR__ . "/cli.php";
final class SiteBackup
{
    public const CONFIG = [
        "config.php",
        "auth.config.local.php",
        "seo.config.local.php",
        "paths.config.local.php",
        "maintenance.config.local.php",
        "mail.config.local.php",
    ];
    private string $root;
    public string $public;
    public function __construct()
    {
        if (!class_exists("ZipArchive") || !function_exists("proc_open")) {
            throw new RuntimeException(
                "Enable PHP ZipArchive and proc_open. MySQL CLI clients must be installed.",
            );
        }
        $this->root = dirname(__DIR__);
        $paths = require $this->root . "/paths.config.php";
        $public = realpath($paths["public_directory"]);
        if (!$public || $this->inside($this->root, $public)) {
            throw new RuntimeException(
                "Private PHP must be outside an existing public directory.",
            );
        }
        $this->public = $public;
    }
    private function inside(string $path, string $parent): bool
    {
        return $path === $parent ||
            str_starts_with($path, $parent . DIRECTORY_SEPARATOR);
    }
    public function privateDirectory(string $path): string
    {
        // Resolve an existing parent before creating anything, including symlinked paths.
        if (!is_dir($path)) {
            $parent = realpath(dirname($path));
            if (!$parent) {
                throw new RuntimeException(
                    "Backup parent directory must already exist.",
                );
            }
            $path = $parent . "/" . basename($path);
        } else {
            $path = realpath($path);
        }
        foreach (
            [
                $this->public,
                dirname($this->root) . "/public",
                dirname($this->root) . "/dist",
            ]
            as $web
        ) {
            $web = realpath($web);
            if ($web && $this->inside($path, $web)) {
                throw new RuntimeException(
                    "Backup/staging directories cannot be inside a public directory.",
                );
            }
        }
        if (!is_dir($path) && !mkdir($path, 0700)) {
            throw new RuntimeException("Cannot create backup directory.");
        }
        if (!is_writable($path)) {
            throw new RuntimeException("Backup directory is not writable.");
        }
        return $path;
    }
    public function defaultDirectory(): string
    {
        return $this->root . "/storage/backups";
    }
    private function config(): array
    {
        $config = require $this->root . "/config.php";
        if (strtolower($config["db_type"] ?? "mysql") !== "mysql") {
            throw new RuntimeException(
                "Backup/restore currently supports the MySQL installation only.",
            );
        }
        if (!preg_match('/^[a-zA-Z0-9_$-]+$/D', $config["database"] ?? "")) {
            throw new RuntimeException("Unsupported database identifier.");
        }
        return $config;
    }
    private function run(
        array $command,
        string $input,
        string $output,
        string $directory,
    ): void {
        $error = $directory . "/client-error";
        $process = proc_open(
            $command,
            [
                0 => ["file", $input, "r"],
                1 => ["file", $output, "w"],
                2 => ["file", $error, "w"],
            ],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException(
                "Could not start MySQL client. Check executable path and host restrictions.",
            );
        }
        $status = proc_close($process);
        if ($status !== 0) {
            throw new RuntimeException(
                "MySQL client failed. Check client/server compatibility, connection and database permissions. Raw diagnostics are withheld to protect infrastructure details.",
            );
        }
    }
    private function credentials(string $directory): array
    {
        $config = $this->config();
        $quote = fn($v) => '"' .
            str_replace(
                ["\\", "\n", "\r", '"'],
                ["\\\\", "\\n", "\\r", '\\"'],
                (string) $v,
            ) .
            '"';
        $text = "[client]\n";
        foreach (["host", "username", "password", "port"] as $key) {
            if (isset($config[$key])) {
                $text .=
                    ($key === "username" ? "user" : $key) .
                    "=" .
                    $quote($config[$key]) .
                    "\n";
            }
        }
        $file = $directory . "/client.cnf";
        if (file_put_contents($file, $text) === false) {
            throw new RuntimeException(
                "Cannot write temporary client configuration.",
            );
        }
        chmod($file, 0600);
        return [$config["database"], $file];
    }
    public static function remove(string $directory): void
    {
        if (is_link($directory) || is_file($directory)) {
            unlink($directory);
            return;
        }
        if (!is_dir($directory)) {
            return;
        }
        foreach (new DirectoryIterator($directory) as $entry) {
            if (!$entry->isDot()) {
                self::remove($entry->getPathname());
            }
        }
        rmdir($directory);
    }
    private function stage(string $parent): string
    {
        $path = $parent . "/.work-" . bin2hex(random_bytes(10));
        if (!mkdir($path, 0700)) {
            throw new RuntimeException(
                "Could not create private work directory.",
            );
        }
        return $path;
    }
    public function create(
        string $directory,
        string $dumpClient = "mysqldump",
    ): string {
        $directory = $this->privateDirectory($directory);
        $work = $this->stage($directory);
        $file =
            $directory .
            "/backup-" .
            gmdate("Ymd-His") .
            "-" .
            bin2hex(random_bytes(4)) .
            ".zip";
        $partial = $file . ".partial";
        $zip = new ZipArchive();
        $opened = false;
        try {
            [$database, $credentials] = $this->credentials($work);
            $args = [
                $dumpClient,
                "--defaults-file=" . $credentials,
                "--single-transaction",
                "--quick",
                "--hex-blob",
                "--no-tablespaces",
                "--routines",
                "--events",
                "--triggers",
            ];
            // Oracle client options are not accepted by MariaDB clients.
            $version = $work . "/version";
            $this->run(
                [$dumpClient, "--version"],
                PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null",
                $version,
                $work,
            );
            if (
                !str_contains(
                    strtolower(file_get_contents($version)),
                    "mariadb",
                )
            ) {
                $args[] = "--set-gtid-purged=OFF";
            }
            $args[] = $database;
            $sql = $work . "/database.sql";
            $this->run(
                $args,
                PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null",
                $sql,
                $work,
            );
            if (!is_file($sql) || filesize($sql) === 0) {
                throw new RuntimeException("Database dump is empty.");
            }
            if (
                $zip->open($partial, ZipArchive::CREATE | ZipArchive::EXCL) !==
                true
            ) {
                throw new RuntimeException("Cannot create backup archive.");
            }
            $opened = true;
            $files = ["database.sql" => $sql];
            foreach (self::CONFIG as $name) {
                if (is_file($this->root . "/" . $name)) {
                    if (is_link($this->root . "/" . $name)) {
                        throw new RuntimeException(
                            "Symlinked private configuration is not supported.",
                        );
                    }
                    $files["config/" . $name] = $this->root . "/" . $name;
                }
            }
            $uploads = $this->public . "/uploads";
            if (!is_dir($uploads) || is_link($uploads)) {
                throw new RuntimeException(
                    "Uploads directory is missing or symlinked.",
                );
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $uploads,
                    FilesystemIterator::SKIP_DOTS,
                ),
            );
            foreach ($iterator as $entry) {
                if ($entry->isLink() || !$entry->isFile()) {
                    throw new RuntimeException(
                        "Uploads must contain regular files only.",
                    );
                }
                $relative = substr($entry->getPathname(), strlen($uploads) + 1);
                $files[
                    "uploads/" .
                        str_replace(DIRECTORY_SEPARATOR, "/", $relative)
                ] = $entry->getPathname();
            }
            if (!isset($files["uploads/.htaccess"])) {
                throw new RuntimeException(
                    "Upload protection file is missing.",
                );
            }
            $manifest = [
                "format" => 1,
                "created_at" => gmdate("c"),
                "php_version" => PHP_VERSION,
                "git_commit" => null,
                "files" => [],
            ];
            if (function_exists("proc_open")) {
                try {
                    $this->run(
                        [
                            "git",
                            "-C",
                            dirname($this->root),
                            "rev-parse",
                            "HEAD",
                        ],
                        PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null",
                        $work . "/revision",
                        $work,
                    );
                    $revision = trim(file_get_contents($work . "/revision"));
                    if (preg_match('/^[a-f0-9]{40,64}$/D', $revision)) {
                        $manifest["git_commit"] = $revision;
                    }
                } catch (Throwable) {
                }
            }
            if (count($files) + 1 > 100000) {
                throw new RuntimeException(
                    "Too many files for the backup format.",
                );
            }
            $total = 0;
            foreach ($files as $name => $path) {
                if (
                    preg_match('#(?:^|/)\.\.?(/|$)|[\\\\\x00-\x1f]#', $name) ||
                    str_contains($name, ":")
                ) {
                    throw new RuntimeException(
                        "Unsafe upload filename; rename it before backing up.",
                    );
                }
                $bytes = filesize($path);
                $hash = hash_file("sha256", $path);
                if ($bytes === false || !is_string($hash)) {
                    throw new RuntimeException("Could not read backup input.");
                }
                $total += $bytes;
                if ($total > 20 * 1024 * 1024 * 1024) {
                    throw new RuntimeException(
                        "Backup exceeds the 20 GiB expanded safety limit.",
                    );
                }
                $manifest["files"][$name] = [
                    "bytes" => $bytes,
                    "sha256" => $hash,
                ];
                if (!$zip->addFile($path, $name)) {
                    throw new RuntimeException("Cannot add backup file.");
                }
            }
            $json = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            );
            if (strlen($json) > 5000000) {
                throw new RuntimeException(
                    "Too many filenames for the backup manifest.",
                );
            }
            if (!$zip->addFromString("manifest.json", $json)) {
                throw new RuntimeException("Cannot write backup manifest.");
            }
            $closed = $zip->close();
            $opened = false;
            if (!$closed) {
                throw new RuntimeException(
                    "Could not finish archive; check free disk space.",
                );
            }
            chmod($partial, 0600);
            if (!rename($partial, $file)) {
                throw new RuntimeException(
                    "Could not publish completed backup.",
                );
            }
            return $file;
        } finally {
            if ($opened) {
                try {
                    $zip->close();
                } catch (Throwable) {
                }
            }
            if (is_file($partial)) {
                @unlink($partial);
            }
            self::remove($work);
        }
    }
    public function unpack(string $archive, string $directory): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Cannot open backup archive.");
        }
        try {
            $stat = $zip->statName("manifest.json");
            if (!$stat || $stat["size"] > 5000000) {
                throw new RuntimeException("Missing or oversized manifest.");
            }
            $manifest = json_decode(
                $zip->getFromName("manifest.json"),
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
            if (
                ($manifest["format"] ?? null) !== 1 ||
                !is_string($manifest["created_at"] ?? null) ||
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D',
                    $manifest["created_at"],
                ) ||
                !is_array($manifest["files"] ?? null) ||
                !isset(
                    $manifest["files"]["database.sql"],
                    $manifest["files"]["config/config.php"],
                    $manifest["files"]["uploads/.htaccess"],
                )
            ) {
                throw new RuntimeException("Incomplete or unsupported backup.");
            }
            if (
                $zip->numFiles !== count($manifest["files"]) + 1 ||
                $zip->numFiles > 100000
            ) {
                throw new RuntimeException(
                    "Unexpected archive entries or too many files.",
                );
            }
            $seen = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry["name"];
                if (isset($seen[$name])) {
                    throw new RuntimeException("Duplicate archive entry.");
                }
                $seen[$name] = true;
                if ($name === "manifest.json") {
                    continue;
                }
                $expected = $manifest["files"][$name] ?? null;
                $valid =
                    $name === "database.sql" ||
                    in_array(
                        $name,
                        array_map(fn($n) => "config/" . $n, self::CONFIG),
                        true,
                    ) ||
                    str_starts_with($name, "uploads/");
                if (
                    !$valid ||
                    preg_match('#(?:^|/)\.\.?(/|$)|[\\\\\x00-\x1f]#', $name) ||
                    str_starts_with($name, "/") ||
                    str_contains($name, ":") ||
                    !$expected ||
                    !is_int($expected["bytes"] ?? null) ||
                    $expected["bytes"] !== $entry["size"] ||
                    !preg_match('/^[a-f0-9]{64}$/D', $expected["sha256"] ?? "")
                ) {
                    throw new RuntimeException(
                        "Unsafe path or invalid archive metadata.",
                    );
                }
                $total += $entry["size"];
                if ($total > 20 * 1024 * 1024 * 1024) {
                    throw new RuntimeException(
                        "Archive exceeds the 20 GiB expanded safety limit.",
                    );
                }
                $path = $directory . "/" . $name;
                if (
                    !is_dir(dirname($path)) &&
                    !mkdir(dirname($path), 0700, true)
                ) {
                    throw new RuntimeException(
                        "Cannot create staging directory.",
                    );
                }
                $input = $zip->getStream($name);
                $output = fopen($path, "xb");
                if (!$input || !$output) {
                    throw new RuntimeException("Cannot stage archive file.");
                }
                try {
                    $bytes = stream_copy_to_stream(
                        $input,
                        $output,
                        $entry["size"] + 1,
                    );
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if (
                    $bytes !== $expected["bytes"] ||
                    !hash_equals(
                        $expected["sha256"],
                        hash_file("sha256", $path),
                    )
                ) {
                    throw new RuntimeException(
                        "Backup checksum verification failed.",
                    );
                }
            }
            return $manifest;
        } finally {
            $zip->close();
        }
    }
    public function restore(
        string $archive,
        string $directory,
        string $mysql,
        string $dump,
        bool $withConfig,
        bool $verify,
    ): array {
        $directory = $this->privateDirectory($directory);
        $work = $this->stage($directory);
        $old = null;
        $recovery = null;
        try {
            $manifest = $this->unpack($archive, $work);
            if ($verify) {
                return ["manifest" => $manifest, "recovery" => null];
            }
            // Preflight client connectivity before making a recovery backup or changing data.
            [$database, $credentials] = $this->credentials($work);
            $this->run(
                [
                    $mysql,
                    "--defaults-file=" . $credentials,
                    "--database=" . $database,
                    "--execute=SELECT 1",
                ],
                PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null",
                $work . "/probe",
                $work,
            );
            $db = require $this->root . "/db.php";
            $hasUsers =
                $db->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
            $oldVersion = $hasUsers
                ? (int) $db
                    ->query(
                        "SELECT COALESCE(MAX(session_version),0) FROM users",
                    )
                    ->fetchColumn()
                : 0;
            $recovery = $this->create($directory, $dump);
            echo "Pre-restore recovery backup: " . $recovery . PHP_EOL;
            if (
                !is_writable($this->public) ||
                is_link($this->public . "/uploads")
            ) {
                throw new RuntimeException(
                    "Public directory must be writable and uploads must not be symlinked.",
                );
            }
            // Stage uploads on the destination filesystem, before the destructive import.
            $incoming = $this->public . "/.restore-" . bin2hex(random_bytes(8));
            if (!mkdir($incoming, 0700)) {
                throw new RuntimeException("Cannot stage restored uploads.");
            }
            try {
                $this->copyUploads($work . "/uploads", $incoming);
                $this->emptyTarget($db);
                $this->run(
                    [
                        $mysql,
                        "--defaults-file=" . $credentials,
                        "--binary-mode",
                        "--local-infile=0",
                        "--database=" . $database,
                    ],
                    $work . "/database.sql",
                    $work . "/import-output",
                    $work,
                );
                if (
                    $db->query("SHOW TABLES LIKE 'users'")->fetchColumn() !==
                    false
                ) {
                    $restoredVersion = (int) $db
                        ->query(
                            "SELECT COALESCE(MAX(session_version),0) FROM users",
                        )
                        ->fetchColumn();
                    $version = max($oldVersion, $restoredVersion) + 1;
                    if ($version > 2147483647) {
                        throw new RuntimeException(
                            "Session version range exhausted; recovery requires manual intervention.",
                        );
                    }
                    $db->exec(
                        "UPDATE users SET session_version=" . (int) $version,
                    );
                }
                if (
                    $db
                        ->query("SHOW TABLES LIKE 'password_resets'")
                        ->fetchColumn() !== false
                ) {
                    $db->exec("DELETE FROM password_resets");
                }
                if (
                    $db
                        ->query("SHOW TABLES LIKE 'two_factor_challenges'")
                        ->fetchColumn() !== false
                ) {
                    $db->exec("DELETE FROM two_factor_challenges");
                }
                $old =
                    $this->public .
                    "/.previous-uploads-" .
                    bin2hex(random_bytes(8));
                if (!rename($this->public . "/uploads", $old)) {
                    throw new RuntimeException(
                        "Database imported, but old uploads could not be moved. Recover using the pre-restore backup.",
                    );
                }
                if (!rename($incoming, $this->public . "/uploads")) {
                    rename($old, $this->public . "/uploads");
                    $old = null;
                    throw new RuntimeException(
                        "Database imported, but uploads swap failed. Recover using the pre-restore backup.",
                    );
                }
                chmod($this->public . "/uploads", 0755);
            } finally {
                if (is_dir($incoming)) {
                    self::remove($incoming);
                }
            }
            if ($withConfig) {
                foreach (self::CONFIG as $name) {
                    if (!is_file($work . "/config/" . $name)) {
                        continue;
                    }
                    $temporary =
                        $this->root .
                        "/.restore-config-" .
                        bin2hex(random_bytes(8));
                    if (!copy($work . "/config/" . $name, $temporary)) {
                        throw new RuntimeException(
                            "Data restored but configuration copy failed.",
                        );
                    }
                    chmod($temporary, 0600);
                    if (!rename($temporary, $this->root . "/" . $name)) {
                        unlink($temporary);
                        throw new RuntimeException(
                            "Data restored but configuration replacement failed.",
                        );
                    }
                }
            }
            if ($old) {
                self::remove($old);
                $old = null;
            }
            return ["manifest" => $manifest, "recovery" => $recovery];
        } catch (Throwable $e) {
            if ($old) {
                echo "Previous uploads retained at " . $old . PHP_EOL;
            }
            if ($recovery) {
                echo "If data changed, recover from " .
                    $recovery .
                    ". Keep the site offline." .
                    PHP_EOL;
            }
            throw $e;
        } finally {
            self::remove($work);
        }
    }
    private function emptyTarget(PDO $db): void
    {
        // Restore is a full replacement of the configured database, including objects
        // created after the snapshot. It must be a dedicated application database.
        $quote = fn(string $name) => "`" . str_replace("`", "``", $name) . "`";
        $tables = $db->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM);
        $routines = $db
            ->query(
                "SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()",
            )
            ->fetchAll(PDO::FETCH_NUM);
        $events = $db
            ->query(
                "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()",
            )
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($events as $event) {
            $db->exec("DROP EVENT " . $quote($event));
        }
        foreach ($routines as [$name, $type]) {
            if (!in_array($type, ["PROCEDURE", "FUNCTION"], true)) {
                throw new RuntimeException(
                    "Unsupported database routine type.",
                );
            }
            $db->exec("DROP " . $type . " " . $quote($name));
        }
        foreach ($tables as [$name, $type]) {
            if ($type === "VIEW") {
                $db->exec("DROP VIEW " . $quote($name));
            }
        }
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        try {
            foreach ($tables as [$name, $type]) {
                if ($type !== "VIEW") {
                    $db->exec("DROP TABLE " . $quote($name));
                }
            }
        } finally {
            $db->exec("SET FOREIGN_KEY_CHECKS=1");
        }
    }
    private function copyUploads(string $source, string $destination): void
    {
        foreach (new DirectoryIterator($source) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            $target = $destination . "/" . $entry->getFilename();
            if ($entry->isDir()) {
                mkdir($target, 0755);
                $this->copyUploads($entry->getPathname(), $target);
            } elseif (
                !copy($entry->getPathname(), $target) ||
                !chmod($target, 0644)
            ) {
                throw new RuntimeException("Cannot stage restored uploads.");
            }
        }
    }
}
