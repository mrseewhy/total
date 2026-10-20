<?php
declare(strict_types=1);
require_once __DIR__ . "/../console/cli.php";
cliOnly();
if (in_array("--help", $argv, true)) {
    echo "Usage: php php/commands/install.php\nInteractive first installation: private configuration, migrations and initial owner.\nExisting configuration and users are preserved. MySQL is currently required by the full migration set.\n";
    exit();
}
if (count($argv) !== 1) {
    fwrite(STDERR, "Unknown option. Use --help.\n");
    exit(1);
}
try {
    if (PHP_VERSION_ID < 80300) {
        throw new RuntimeException("PHP 8.3 or newer is required.");
    }
    foreach (["pdo_mysql", "mbstring", "fileinfo", "session"] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException(
                "Enable PHP extension {$extension} first.",
            );
        }
    }
    if (!function_exists("passthru")) {
        throw new RuntimeException(
            "CLI passthru is required to run migrations. Use the manual installation guide instead.",
        );
    }
    echo "Install Haven CMS. Use an empty database for a new project. Existing records are preserved.\n";
    $root = dirname(__DIR__);
    if (!is_file($root . "/config.php")) {
        $config = [
            "db_type" => "mysql",
            "host" => prompt("Database host", "localhost"),
        ];
        $port = prompt("Database port", "3306");
        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException("Invalid port.");
        }
        $config["port"] = (int) $port;
        $config["database"] = trim(prompt("Database name"));
        $config["username"] = trim(prompt("Database username"));
        $config["password"] = prompt("Database password", null, true);
        if ($config["database"] === "" || $config["username"] === "") {
            throw new RuntimeException(
                "Database name and username are required.",
            );
        }
        writePrivateConfig($root . "/config.php", $config);
        echo "Created private database configuration.\n";
    } else {
        echo "Preserved existing database configuration.\n";
    }
    $config = require $root . "/config.php";
    if (($config["db_type"] ?? "mysql") !== "mysql") {
        throw new RuntimeException(
            "The current initial migration requires MySQL. No migrations were run.",
        );
    }
    $db = require $root . "/db.php";
    if (!is_file($root . "/auth.config.local.php")) {
        $url = installationUrl(
            trim(prompt("Website root URL", "http://localhost:5173")),
        );
        $sender = trim(
            prompt("Password-reset sender email (blank to configure later)"),
        );
        if ($sender !== "" && !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid sender email.");
        }
        writePrivateConfig($root . "/auth.config.local.php", [
            "app_url" => $url,
            "mail_from" => $sender,
            "secure_cookie" => str_starts_with($url, "https://"),
        ]);
    } else {
        echo "Preserved existing authentication configuration.\n";
    }
    if (!is_file($root . "/paths.config.local.php")) {
        $directory = prompt(
            "Absolute public directory (public_html on shared hosting)",
            dirname($root) . "/public",
        );
        $real = realpath($directory);
        if (
            $real === false ||
            !is_dir($real) ||
            $real === $root ||
            str_starts_with($root, $real . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException(
                "Choose an existing public directory that does not contain private PHP.",
            );
        }
        writePrivateConfig($root . "/paths.config.local.php", [
            "public_directory" => $real,
        ]);
    } else {
        echo "Preserved existing public-directory configuration.\n";
    }
    $paths = require $root . "/paths.config.php";
    $public = realpath($paths["public_directory"]);
    if (
        !$public ||
        $public === $root ||
        str_starts_with($root, $public . DIRECTORY_SEPARATOR)
    ) {
        throw new RuntimeException(
            "Private PHP must be outside the public directory. Fix paths.config.local.php.",
        );
    }
    foreach ([$public . "/uploads", $root . "/storage"] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException(
                "Could not create runtime directory. Check permissions.",
            );
        }
        if (!is_writable($directory)) {
            throw new RuntimeException(
                "Runtime directories must be writable by PHP.",
            );
        }
    }
    if (!is_file($public . "/uploads/.htaccess")) {
        throw new RuntimeException(
            "Deploy the uploads/.htaccess protection file from the build before continuing.",
        );
    }
    echo "Applying pending migrations (back up an existing project first).\n";
    if (
        strtolower(trim(prompt("Run migrations now? yes/no", "no"))) !== "yes"
    ) {
        echo "Stopped before migrations. Rerun to continue.\n";
        exit();
    }
    passthru(
        escapeshellarg(PHP_BINARY) .
            " " .
            escapeshellarg($root . "/migrate.php") .
            " migrate",
        $status,
    );
    if ($status !== 0) {
        throw new RuntimeException(
            "Migration failed. Installation is incomplete; review the migration output before retrying.",
        );
    }
    require_once $root . "/models/User.php";
    require_once $root . "/validation/AuthValidation.php";
    if (
        (int) $db
            ->query(
                "SELECT COUNT(*) FROM users WHERE role='Admin' AND is_active=TRUE",
            )
            ->fetchColumn() === 0
    ) {
        if ((int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
            throw new RuntimeException(
                "Existing users have no active Admin. Recover the owner account manually; installation will not create another owner.",
            );
        }
        $name = trim(prompt("Owner name"));
        if ($name === "" || mb_strlen($name) > 100) {
            throw new RuntimeException("Owner name must be 1–100 characters.");
        }
        $email = AuthValidation::email(["email" => prompt("Owner email")]);
        $password = AuthValidation::newPassword([
            "password" => prompt("Owner password (12–72 bytes)", null, true),
            "password_confirmation" => prompt(
                "Confirm owner password",
                null,
                true,
            ),
        ]);
        // Serialize bootstrap so concurrent installers cannot both create an owner.
        $db->beginTransaction();
        try {
            $db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            if (
                (int) $db
                    ->query("SELECT COUNT(*) FROM users")
                    ->fetchColumn() !== 0
            ) {
                throw new RuntimeException(
                    "A user was created concurrently. Rerun installation.",
                );
            }
            (new User($db))->create($name, $email, $password);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        unset($password);
        echo "Owner created.\n";
    } else {
        echo "Preserved existing owner and all user accounts.\n";
    }
    echo "Configure mail.config.local.php and enable PHP mail delivery on the host before using password recovery.\n";
    echo "Installation steps completed. Sign in at /site-admin/login.\nRun php php/commands/health-check.php (add --production on the host).\nNo sample content was inserted.\n";
} catch (Throwable $e) {
    // Never print connection exceptions, DSNs or credentials.
    fwrite(
        STDERR,
        ($e instanceof PDOException
            ? "Database operation failed. Check private configuration and server logs."
            : $e->getMessage()) .
            "\nInstallation is incomplete; existing configuration was preserved.\n",
    );
    exit(1);
}
