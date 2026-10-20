<?php
declare(strict_types=1);
function cliOnly(): void
{
    if (PHP_SAPI !== "cli") {
        http_response_code(403);
        exit();
    }
    ini_set("display_errors", "0");
}
function prompt(
    string $label,
    ?string $default = null,
    bool $secret = false,
): string {
    fwrite(STDOUT, $label . ($default !== null ? " [{$default}]" : "") . ": ");
    $state = null;
    if ($secret) {
        if (
            PHP_OS_FAMILY === "Windows" ||
            !function_exists("shell_exec") ||
            !function_exists("stream_isatty") ||
            !stream_isatty(STDIN)
        ) {
            throw new RuntimeException(
                "Hidden input requires a Unix terminal. Configure private files manually; no password was read.",
            );
        }
        $state = shell_exec("stty -g < /dev/tty 2>/dev/null");
        if (
            !is_string($state) ||
            !preg_match('/^[a-zA-Z0-9:=; ._\s-]+$/', $state)
        ) {
            throw new RuntimeException("Could not enable hidden input.");
        }
        if (
            shell_exec("stty -echo < /dev/tty 2>/dev/null && printf hidden") !==
            "hidden"
        ) {
            throw new RuntimeException(
                "Could not disable terminal echo; no password was read.",
            );
        }
    }
    try {
        $line = fgets(STDIN);
        if ($line === false) {
            throw new RuntimeException(
                "Input ended. Rerun installation to continue.",
            );
        }
    } finally {
        if ($state !== null) {
            shell_exec(
                "stty " .
                    escapeshellarg(trim($state)) .
                    " < /dev/tty 2>/dev/null",
            );
            fwrite(STDOUT, PHP_EOL);
        }
    }
    $value = rtrim($line, "\r\n");
    return $value === "" && $default !== null ? $default : $value;
}
function writePrivateConfig(string $file, array $config): void
{
    $previous = umask(0077);
    try {
        $handle = @fopen($file, "x");
        if (!$handle) {
            throw new RuntimeException(
                "Configuration exists or cannot be created; it was not overwritten.",
            );
        }
        try {
            $text =
                "<?php\ndeclare(strict_types=1);\nreturn " .
                var_export($config, true) .
                ";\n";
            if (fwrite($handle, $text) !== strlen($text)) {
                throw new RuntimeException("Could not write configuration.");
            }
        } catch (Throwable $e) {
            fclose($handle);
            unlink($file);
            throw $e;
        }
        fclose($handle);
    } finally {
        umask($previous);
    }
}
function installationUrl(string $url): string
{
    $parts = parse_url($url);
    if (
        !$parts ||
        !in_array($parts["scheme"] ?? "", ["http", "https"], true) ||
        empty($parts["host"]) ||
        isset($parts["user"]) ||
        isset($parts["pass"]) ||
        isset($parts["query"]) ||
        isset($parts["fragment"]) ||
        !in_array($parts["path"] ?? "", ["", "/"], true)
    ) {
        throw new InvalidArgumentException(
            "Use an absolute website root URL without credentials, query or subdirectory.",
        );
    }
    $local = in_array(
        strtolower($parts["host"]),
        ["localhost", "127.0.0.1", "[::1]", "::1"],
        true,
    );
    if (!$local && $parts["scheme"] !== "https") {
        throw new InvalidArgumentException("Use HTTPS for a hosted website.");
    }
    return rtrim($url, "/");
}
