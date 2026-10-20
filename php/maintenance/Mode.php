<?php
declare(strict_types=1);
final class MaintenanceMode
{
    public static function storage(): string
    {
        return dirname(__DIR__) . "/storage";
    }
    private static function flag(): string
    {
        return self::storage() . "/maintenance.json";
    }
    public static function status(): array
    {
        clearstatcache(true, self::flag());
        if (!is_file(self::flag())) {
            return ["enabled" => false];
        }
        // A malformed marker fails closed; never expose private file contents.
        $data = json_decode((string) @file_get_contents(self::flag()), true);
        return [
            "enabled" => true,
            "since" => is_string($data["since"] ?? null)
                ? $data["since"]
                : null,
            "message" => is_string($data["message"] ?? null)
                ? $data["message"]
                : "We are carrying out maintenance. Please try again shortly.",
            "retry_after" =>
                is_int($data["retry_after"] ?? null) &&
                $data["retry_after"] >= 60 &&
                $data["retry_after"] <= 86400
                    ? $data["retry_after"]
                    : 300,
        ];
    }
    private static function lock(string $name, int $operation): mixed
    {
        $storage = self::storage();
        if (
            !is_dir($storage) &&
            !@mkdir($storage, 0750, true) &&
            !is_dir($storage)
        ) {
            throw new RuntimeException(
                "Cannot create private maintenance storage.",
            );
        }
        $previous = umask(0007);
        try {
            $handle = @fopen($storage . "/" . $name, "c");
        } finally {
            umask($previous);
        }
        if (!$handle || !flock($handle, $operation | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException(
                "Another request or maintenance operation is still running. Try again shortly.",
            );
        }
        return $handle;
    }
    public static function release(array $locks): void
    {
        foreach (array_reverse($locks) as $lock) {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
    public static function down(
        string $message,
        int $retryAfter,
        int $wait,
    ): array {
        if (
            $message === "" ||
            strlen($message) > 300 ||
            preg_match('/[\x00-\x1f\x7f]/', $message) ||
            $retryAfter < 60 ||
            $retryAfter > 86400 ||
            $wait < 0 ||
            $wait > 60
        ) {
            throw new InvalidArgumentException(
                "Use a message of 1–300 bytes, retry-after 60–86400 seconds and wait 0–60 seconds.",
            );
        }
        $operation = self::lock(".maintenance-operation.lock", LOCK_EX);
        $temporary =
            self::storage() . "/.maintenance-" . bin2hex(random_bytes(8));
        $traffic = null;
        try {
            $state = self::status();
            $state = [
                "enabled" => true,
                "since" => $state["since"] ?? gmdate("c"),
                "message" => $message,
                "retry_after" => $retryAfter,
            ];
            if (
                file_put_contents(
                    $temporary,
                    json_encode($state, JSON_THROW_ON_ERROR),
                    LOCK_EX,
                ) === false ||
                !chmod($temporary, 0640) ||
                !rename($temporary, self::flag())
            ) {
                throw new RuntimeException(
                    "Could not publish maintenance mode.",
                );
            }
            $deadline = microtime(true) + $wait;
            do {
                try {
                    $traffic = self::lock(".traffic.lock", LOCK_EX);
                    break;
                } catch (RuntimeException $e) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException(
                            "Maintenance is enabled, but existing requests have not drained. Wait and rerun down before backup/restore.",
                        );
                    }
                    usleep(50000);
                }
            } while (true);
            return $state;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            self::release([$operation, $traffic]);
        }
    }
    public static function up(): void
    {
        $operation = self::lock(".maintenance-operation.lock", LOCK_EX);
        try {
            if (is_file(self::flag()) && !unlink(self::flag())) {
                throw new RuntimeException(
                    "Could not remove maintenance marker.",
                );
            }
        } finally {
            self::release([$operation]);
        }
    }
    /** Hold through a CLI mutation. Blocks reopening, new PHP requests and other exclusive work. */
    public static function commandLock(
        bool $requireDown = true,
        bool $exclusive = true,
    ): array {
        $operation = self::lock(".maintenance-operation.lock", LOCK_SH);
        try {
            if ($requireDown && !self::status()["enabled"]) {
                throw new RuntimeException(
                    "Enable maintenance first: php php/commands/maintenance.php down",
                );
            }
            $traffic = self::lock(
                ".traffic.lock",
                $exclusive ? LOCK_EX : LOCK_SH,
            );
            return [$operation, $traffic];
        } catch (Throwable $e) {
            self::release([$operation]);
            throw $e;
        }
    }
    /** Hold a shared lock until request shutdown; check twice to close the down/request race. */
    public static function enterRequest(bool $api): bool
    {
        if (self::status()["enabled"]) {
            self::unavailable($api);
            return false;
        }
        try {
            $lock = self::lock(".traffic.lock", LOCK_SH);
        } catch (Throwable) {
            self::unavailable($api);
            return false;
        }
        if (self::status()["enabled"]) {
            self::release([$lock]);
            self::unavailable($api);
            return false;
        }
        register_shutdown_function(static function () use ($lock) {
            self::release([$lock]);
        });
        return true;
    }
    private static function unavailable(bool $api): void
    {
        $state = self::status();
        $message =
            $state["message"] ??
            "We are carrying out maintenance. Please try again shortly.";
        http_response_code(503);
        header("Retry-After: " . ($state["retry_after"] ?? 300));
        header("Cache-Control: no-store");
        if ($api) {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode([
                "success" => false,
                "maintenance" => true,
                "message" => $message,
            ]);
        } else {
            header("Content-Type: text/html; charset=utf-8");
            $message = htmlspecialchars(
                $message,
                ENT_QUOTES | ENT_SUBSTITUTE,
                "UTF-8",
            );
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Temporarily unavailable</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#faf9f6;color:#1f2937;font:16px/1.6 system-ui,sans-serif}main{max-width:34rem;margin:2rem;padding:2.5rem;border:1px solid #e5e7eb;border-radius:1rem;background:white}h1{line-height:1.2}p{color:#647067}</style></head><body><main><h1>We’ll be back shortly.</h1><p>' .
                $message .
                "</p></main></body></html>";
        }
    }
}
