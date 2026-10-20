<?php
declare(strict_types=1);
function healthChecks(bool $production): array
{
    $checks = [];
    $add = function (string $name, string $status, string $message) use (
        &$checks,
    ): void {
        $checks[] = compact("name", "status", "message");
    };
    $root = dirname(__DIR__);
    $add(
        "php",
        PHP_VERSION_ID >= 80300 ? "pass" : "fail",
        "PHP 8.3 or newer is required; running " . PHP_VERSION . ".",
    );
    foreach (
        ["PDO", "pdo_mysql", "mbstring", "fileinfo", "session", "json"]
        as $ext
    ) {
        $add(
            "extension:" . $ext,
            extension_loaded($ext) ? "pass" : "fail",
            extension_loaded($ext)
                ? "Available."
                : "Enable this PHP extension.",
        );
    }
    $public = null;
    try {
        $paths = require $root . "/paths.config.php";
        $public = realpath($paths["public_directory"] ?? "");
        if (!$public || !is_dir($public)) {
            throw new RuntimeException();
        }
        $private = realpath($root);
        $exposed =
            $private === $public ||
            str_starts_with($private, $public . DIRECTORY_SEPARATOR);
        $add(
            "private-directory",
            $exposed ? "fail" : "pass",
            $exposed
                ? "Private PHP is inside the public directory. Move it outside the document root."
                : "Private PHP is outside the configured public directory.",
        );
        $upload = $public . "/uploads";
        $add(
            "uploads",
            is_dir($upload) && is_writable($upload) ? "pass" : "fail",
            "Uploads directory must exist and be writable by the web PHP user. CLI permissions may differ.",
        );
        $rules = is_file($upload . "/.htaccess")
            ? file_get_contents($upload . "/.htaccess")
            : "";
        $add(
            "upload-rules",
            str_contains($rules, "Require all denied") &&
            str_contains($rules, "SetHandler default-handler")
                ? "pass"
                : "fail",
            "Upload execution-protection rules must be deployed; verify Apache honors them over HTTP.",
        );
        foreach (
            ["index.php", "api/index.php", ".htaccess", "route-manifest.json"]
            as $file
        ) {
            $add(
                "public:" . $file,
                is_file($public . "/" . $file) ? "pass" : "fail",
                is_file($public . "/" . $file)
                    ? "Present."
                    : "Missing deployed public file.",
            );
        }
        $built =
            is_file($public . "/index.html") &&
            is_dir($public . "/assets") &&
            (bool) glob($public . "/assets/*.js");
        $add(
            "frontend-build",
            $built ? "pass" : ($production ? "fail" : "warn"),
            $built
                ? "Built HTML and JavaScript assets are present."
                : "Build locally and deploy dist contents. A development server is not a production build.",
        );
    } catch (Throwable) {
        $add(
            "public-directory",
            "fail",
            "Public-directory configuration is invalid or inaccessible.",
        );
    }
    foreach (
        [
            "config.php",
            "auth.config.local.php",
            "seo.config.local.php",
            "paths.config.local.php",
            "maintenance.config.local.php",
            "mail.config.local.php",
        ]
        as $file
    ) {
        if (!is_file($root . "/" . $file)) {
            if ($file === "config.php") {
                $add(
                    "database-config",
                    "fail",
                    "Create private database configuration with the installer or example file.",
                );
            }
            continue;
        }
        $mode = fileperms($root . "/" . $file);
        $add(
            "permissions:" . $file,
            $mode !== false && ($mode & 0007) !== 0 ? "warn" : "pass",
            "Private config should be readable only by its owner or trusted PHP group; never use 777.",
        );
    }
    try {
        $auth = require $root . "/auth.config.php";
        $seo = require $root . "/seo.config.php";
        require_once __DIR__ . "/cli.php";
        foreach (
            [
                "reset-url" => $auth["app_url"],
                "canonical-url" => $seo["site_url"],
            ]
            as $name => $url
        ) {
            installationUrl($url);
            $host = strtolower(parse_url($url, PHP_URL_HOST) ?? "");
            $local = in_array(
                $host,
                ["localhost", "127.0.0.1", "[::1]", "::1"],
                true,
            );
            $add(
                $name,
                $production && $local ? "fail" : "pass",
                $production && $local
                    ? "Replace localhost with the real HTTPS domain."
                    : "Valid root URL.",
            );
        }
        $add(
            "url-consistency",
            rtrim($auth["app_url"], "/") === rtrim($seo["site_url"], "/")
                ? "pass"
                : "warn",
            "Password-reset and canonical URLs should use the same website origin.",
        );
        $add(
            "secure-cookie",
            !$production || ($auth["secure_cookie"] ?? false) ? "pass" : "warn",
            "On HTTPS hosting explicitly set secure_cookie=true; CLI cannot verify web-server HTTPS detection.",
        );
        if ($production) {
            $add(
                "indexing",
                $seo["allow_indexing"] ?? false ? "pass" : "warn",
                "Indexing is disabled when allow_indexing is false; keep it disabled intentionally on staging.",
            );
        }
    } catch (Throwable) {
        $add(
            "site-config",
            "fail",
            "Invalid authentication/SEO configuration or website URL.",
        );
    }
    try {
        require_once $root . "/functions/mail.php";
        $mail = mailConfiguration();
        $add(
            "mail-enabled",
            $mail["enabled"] ? "pass" : "warn",
            $mail["enabled"]
                ? "Outgoing PHP mail is enabled in application configuration."
                : "Outgoing mail is disabled. Enable it in mail.config.local.php after host setup.",
        );
        $add(
            "mail-sender",
            $mail["from_address"] !== ""
                ? "pass"
                : ($mail["enabled"]
                    ? "fail"
                    : "warn"),
            "Set a host-approved from_address and verify actual delivery separately.",
        );
        $domain = strtolower(
            substr(strrchr($mail["from_address"], "@") ?: "@", 1),
        );
        if (
            in_array(
                $domain,
                ["example.com", "example.org", "example.net"],
                true,
            ) ||
            preg_match('/\.(?:example|invalid|test|localhost)$/D', $domain)
        ) {
            $add(
                "mail-placeholder",
                $mail["enabled"] ? "fail" : "warn",
                "Replace the example sender domain with the website host-approved sender before enabling delivery.",
            );
        }
        $add(
            "mail-function",
            function_exists("mail")
                ? "pass"
                : ($mail["enabled"]
                    ? "fail"
                    : "warn"),
            function_exists("mail")
                ? "PHP mail() exists; this does not establish transport configuration or delivery."
                : "PHP mail() is disabled/unavailable. Ask the host to enable it.",
        );
    } catch (Throwable) {
        $add(
            "mail-config",
            "fail",
            "Mail configuration is invalid. Check mail.config.local.php fields and header characters.",
        );
    }
    try {
        if (!is_file($root . "/config.php")) {
            throw new RuntimeException();
        }
        $config = require $root . "/config.php";
        if (strtolower($config["db_type"] ?? "mysql") !== "mysql") {
            $add(
                "database-driver",
                "fail",
                "Full installation migrations currently require MySQL. PDO choices alone do not make the schema portable.",
            );
        } else {
            // Do not emit low-level driver diagnostics containing infrastructure details.
            $oldLog = ini_get("error_log");
            ini_set(
                "error_log",
                PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null",
            );
            try {
                $db = require $root . "/db.php";
            } finally {
                ini_set("error_log", $oldLog);
            }
            $db->query("SELECT 1");
            $add(
                "database",
                "pass",
                "Connection and read query succeeded. No records were changed.",
            );
            $files = array_map(
                "basename",
                glob($root . "/migrations/*.php") ?: [],
            );
            try {
                $done = $db
                    ->query("SELECT migration FROM migrations")
                    ->fetchAll(PDO::FETCH_COLUMN);
                $pending = array_values(array_diff($files, $done));
                $unknown = array_values(array_diff($done, $files));
                $add(
                    "migrations",
                    $pending ? "fail" : "pass",
                    $pending
                        ? count($pending) .
                            " pending migration(s). Back up, then run php php/migrate.php migrate."
                        : "No pending migrations.",
                );
                if ($unknown) {
                    $add(
                        "migration-history",
                        "warn",
                        "Applied migration files are missing from this release. Verify deployment history.",
                    );
                }
            } catch (Throwable) {
                $add(
                    "migrations",
                    "fail",
                    "Migration tracking table is missing or unreadable. Run installation/migrations.",
                );
            }
            try {
                $owner = (int) $db
                    ->query(
                        "SELECT COUNT(*) FROM users WHERE role='Admin' AND is_active=TRUE",
                    )
                    ->fetchColumn();
                $add(
                    "owner",
                    $owner > 0 ? "pass" : "fail",
                    $owner > 0
                        ? "Active Admin account exists."
                        : "No active Admin. Complete installation or recover the owner account.",
                );
                require_once $root . "/config/ContentModules.php";
                require_once $root . "/database/crud.php";
                $tables = array_merge(
                    [
                        "users",
                        "contacts",
                        "subscribers",
                        "media",
                        "content_media",
                        "activity_log",
                        "page_metadata",
                        "seo_settings",
                        "rate_limits",
                        "password_resets",
                        "two_factor_challenges",
                        "user_management_lock",
                        "content_lock",
                    ],
                    array_keys(ContentModules::all()),
                );
                foreach ($tables as $table) {
                    try {
                        $db->query(
                            "SELECT 1 FROM " .
                                crudIdentifier($db, $table) .
                                " LIMIT 1",
                        );
                    } catch (Throwable) {
                        $add(
                            "table:" . $table,
                            "fail",
                            "Required table is missing or unreadable. Check migrations and database permissions.",
                        );
                    }
                }
            } catch (Throwable) {
                $add(
                    "schema",
                    "fail",
                    "User schema or module registry is invalid/unavailable. Check migration status.",
                );
            }
        }
    } catch (Throwable) {
        $add(
            "database",
            "fail",
            "Could not connect. Check private configuration, PDO driver and database permissions. Credentials are not displayed.",
        );
    }
    try {
        require_once $root . "/models/SeoMetadata.php";
        $pages = SeoMetadata::pages();
        $add(
            "page-manifest",
            count($pages) > 0 ? "pass" : "fail",
            count($pages) > 0
                ? "Main-page manifest loads successfully."
                : "No public pages registered. Rebuild the frontend.",
        );
    } catch (Throwable) {
        $add(
            "page-manifest",
            "fail",
            "Page manifest or module registry is invalid. Rebuild/deploy public files.",
        );
    }
    $add(
        "private-storage",
        is_dir($root . "/storage") && is_writable($root . "/storage")
            ? "pass"
            : "fail",
        "Private storage must exist and be writable for media deletion quarantine.",
    );
    try {
        require_once $root . "/maintenance/Mode.php";
        $mode = MaintenanceMode::status();
        $add(
            "maintenance-mode",
            $mode["enabled"] ? "warn" : "pass",
            $mode["enabled"]
                ? "Maintenance is enabled. PHP/API traffic remains blocked until maintenance.php up."
                : "Maintenance is off.",
        );
        require_once $root . "/maintenance/Cleanup.php";
        MaintenanceCleanup::config();
        $add(
            "retention-config",
            "pass",
            "Maintenance retention settings are valid. Cleanup defaults to dry run.",
        );
    } catch (Throwable) {
        $add(
            "maintenance-config",
            "fail",
            "Maintenance configuration or helpers are invalid/unavailable.",
        );
    }
    $sessionPath = ini_get("session.save_path");
    if (ini_get("session.save_handler") === "files") {
        $directory = explode(";", $sessionPath);
        $directory = end($directory) ?: sys_get_temp_dir();
        $add(
            "session-storage",
            is_dir($directory) && is_writable($directory) ? "pass" : "warn",
            "CLI session directory must be writable. Verify web PHP uses a writable private session directory too.",
        );
    }
    if ($production) {
        $add(
            "hosting-verification",
            "warn",
            "CLI cannot verify TLS, actual document root, Apache rules, web-user permissions, email delivery or backups. Verify these separately.",
        );
    }
    return $checks;
}
