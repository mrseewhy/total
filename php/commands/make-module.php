<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit();
}
require_once __DIR__ . "/../config/ContentModules.php";
if (count($argv) < 4 || count($argv) > 6) {
    fwrite(
        STDERR,
        "Usage: php php/commands/make-module.php <id> <label> <singular> [public-path] [profile]\nExample: php php/commands/make-module.php testimonials Testimonials testimonial\n",
    );
    exit(1);
}
$lock = fopen(__DIR__ . "/../config/.modules.lock", "c");
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another module creation is running.\n");
    exit(1);
}
$migration = null;
$created = false;
try {
    [$command, $id, $label, $singular] = $argv;
    $path = $argv[4] ?? str_replace("_", "-", $id);
    $profile = $argv[5] ?? "content";
    $file = __DIR__ . "/../config/content-modules.json";
    $raw = json_decode(
        file_get_contents($file),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    ContentModules::validate($raw);
    if (isset($raw[$id])) {
        throw new RuntimeException("This module is already registered.");
    }
    $raw[$id] = [
        "label" => $label,
        "singular" => $singular,
        "path" => $path,
        "profile" => $profile,
        "scheduling" => $profile === "article",
    ];
    ContentModules::validate($raw);
    if (glob(__DIR__ . "/../migrations/*_" . $id . ".php")) {
        throw new RuntimeException(
            "A migration for this module already exists. Restore its registry entry instead of creating a second table.",
        );
    }
    $number = 0;
    foreach (glob(__DIR__ . "/../migrations/*.php") as $existing) {
        if (preg_match("/^(\d+)_/", basename($existing), $match)) {
            $number = max($number, (int) $match[1]);
        }
    }
    $name = sprintf("%03d_%s.php", $number + 1, $id);
    $migration = __DIR__ . "/../migrations/" . $name;
    $source = "<?php\nrequire_once __DIR__ . '/../database/content-schema.php';\nreturn [\n    'up' => fn(PDO \$db) => createContentTable(\$db, '{$id}'),\n    'down' => fn(PDO \$db) => dropContentTable(\$db, '{$id}'),\n];\n";
    $handle = fopen($migration, "x");
    if (!$handle) {
        throw new RuntimeException("Cannot create the migration.");
    }
    $created = true;
    try {
        if (fwrite($handle, $source) !== strlen($source)) {
            throw new RuntimeException(
                "Could not finish writing the migration.",
            );
        }
    } finally {
        fclose($handle);
    }
    $temporary = $file . ".tmp";
    $json =
        json_encode(
            $raw,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
    if (
        file_put_contents($temporary, $json) === false ||
        !rename($temporary, $file)
    ) {
        throw new RuntimeException("Cannot update the module registry.");
    }
    echo "Created registry entry and php/migrations/{$name}.\nNext: php php/migrate.php migrate\nAdmin: /site-admin/{$id}\nPublic: /{$path}\nBuild and deploy normally after migrating. No controller/model/route mappings are needed.\n";
} catch (Throwable $e) {
    if ($created && $migration && is_file($migration)) {
        unlink($migration);
    }
    if (isset($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
    fwrite(STDERR, "Could not create module: " . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
