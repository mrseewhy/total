<?php
declare(strict_types=1);
require_once __DIR__ . "/../console/cli.php";
cliOnly();
$allowed = ["--json", "--production", "--strict", "--help"];
foreach (array_slice($argv, 1) as $arg) {
    if (!in_array($arg, $allowed, true)) {
        fwrite(STDERR, "Unknown option. Use --help.\n");
        exit(1);
    }
}
if (in_array("--help", $argv, true)) {
    echo "Usage: php php/commands/health-check.php [--production] [--json] [--strict]\nRead-only checks; no migrations, configuration writes or mail.\nExit 1: failed check; 0: no failures; --strict returns 2 for warnings only.\n";
    exit();
}
require_once __DIR__ . "/../console/health.php";
$checks = healthChecks(in_array("--production", $argv, true));
$fail = count(array_filter($checks, fn($c) => $c["status"] === "fail"));
$warn = count(array_filter($checks, fn($c) => $c["status"] === "warn"));
if (in_array("--json", $argv, true)) {
    echo json_encode(
        [
            "ok" => $fail === 0,
            "failures" => $fail,
            "warnings" => $warn,
            "checks" => $checks,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ) . PHP_EOL;
} else {
    foreach ($checks as $check) {
        echo strtoupper($check["status"]) .
            " " .
            $check["name"] .
            ": " .
            $check["message"] .
            PHP_EOL;
    }
    echo "Result: {$fail} failed, {$warn} warning(s). No changes were made.\n";
}
exit($fail ? 1 : ($warn && in_array("--strict", $argv, true) ? 2 : 0));
