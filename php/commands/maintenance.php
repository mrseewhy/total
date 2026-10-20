<?php
declare(strict_types=1);
require_once __DIR__ . "/../console/cli.php";
cliOnly();
require_once __DIR__ . "/../maintenance/Mode.php";
if (in_array("--help", $argv, true)) {
    echo "Usage: php php/commands/maintenance.php [status|down|up] [--json] [--message=TEXT] [--retry-after=300] [--wait=30]\nDown blocks all PHP/API access, including Admins, and waits for existing PHP requests to finish. Up reopens the site. No database is required.\n";
    exit();
}
try {
    $command = $argv[1] ?? "status";
    $options = [];
    if (!in_array($command, ["down", "up", "status"], true)) {
        throw new RuntimeException("Use down, up or status.");
    }
    foreach (array_slice($argv, 2) as $arg) {
        if (!str_starts_with($arg, "--")) {
            throw new RuntimeException("Invalid option.");
        }
        [$key, $value] = array_pad(explode("=", substr($arg, 2), 2), 2, true);
        if (
            isset($options[$key]) ||
            !in_array(
                $key,
                $command === "down"
                    ? ["json", "message", "retry-after", "wait"]
                    : ["json"],
                true,
            )
        ) {
            throw new RuntimeException("Invalid or duplicate option.");
        }
        if (
            $key === "json"
                ? $value !== true
                : !is_string($value) || $value === ""
        ) {
            throw new RuntimeException("Invalid option value.");
        }
        if (
            in_array($key, ["retry-after", "wait"], true) &&
            !ctype_digit($value)
        ) {
            throw new RuntimeException("Timing options must be whole seconds.");
        }
        $options[$key] = $value;
    }
    if ($command === "down") {
        $state = MaintenanceMode::down(
            $options["message"] ??
                "We are carrying out maintenance. Please try again shortly.",
            (int) ($options["retry-after"] ?? 300),
            (int) ($options["wait"] ?? 30),
        );
    } else {
        if ($command === "up") {
            MaintenanceMode::up();
        }
        $state = MaintenanceMode::status();
    }
    echo isset($options["json"])
        ? json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL
        : ($state["enabled"]
                ? "Maintenance ON. PHP/API requests are blocked."
                : "Maintenance OFF.") . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
