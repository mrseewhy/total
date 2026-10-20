<?php

declare(strict_types=1);

// Cleanup retention depends on this supported upper bound.
const RATE_LIMIT_MAX_WINDOW_SECONDS = 86400;

/**
 * Fixed-window database rate limiter.
 *
 * Returns true when the request is allowed.
 * Returns false when the limit has been reached.
 *
 * The rate_limits table must have:
 * - identifier VARCHAR(64) PRIMARY KEY
 * - attempts INTEGER NOT NULL
 * - window_start BIGINT NOT NULL
 */
function checkRateLimit(
    PDO $db,
    string $identifier,
    string $action,
    int $limit = 3,
    int $windowSeconds = 600,
): bool {
    if ($identifier === "" || $action === "") {
        throw new InvalidArgumentException(
            "Rate-limit identifier and action are required.",
        );
    }

    if ($limit < 1 || $windowSeconds < 1) {
        throw new InvalidArgumentException(
            "Rate-limit values must be positive.",
        );
    }

    if ($windowSeconds > RATE_LIMIT_MAX_WINDOW_SECONDS) {
        throw new InvalidArgumentException(
            "Rate-limit windows cannot exceed one day.",
        );
    }

    $key = hash("sha256", $action . ":" . $identifier);
    $now = time();
    $cutoff = $now - $windowSeconds;

    /*
     * Atomic update:
     * - Reset an expired window.
     * - Increment an active window if below the limit.
     * - Leave exhausted windows unchanged.
     *
     * Concurrent updates are serialized by the database.
     */
    $update = $db->prepare(
        'UPDATE rate_limits
         SET attempts = CASE
                 WHEN window_start <= :cutoff_attempts
                 THEN 1
                 ELSE attempts + 1
             END,
             window_start = CASE
                 WHEN window_start <= :cutoff_window
                 THEN :now_window
                 ELSE window_start
             END
         WHERE identifier = :identifier
           AND (
               window_start <= :cutoff_where
               OR attempts < :request_limit
           )',
    );

    $params = [
        "cutoff_attempts" => $cutoff,
        "cutoff_window" => $cutoff,
        "now_window" => $now,
        "identifier" => $key,
        "cutoff_where" => $cutoff,
        "request_limit" => $limit,
    ];

    $update->execute($params);

    if ($update->rowCount() === 1) {
        return true;
    }

    /*
     * No row was updated:
     * Either the key is new or its limit is exhausted.
     *
     * Try inserting a new record. A concurrent insert may
     * win first, so retry the atomic update in that case.
     */
    try {
        $insert = $db->prepare(
            'INSERT INTO rate_limits
             (identifier, attempts, window_start)
             VALUES (:identifier, 1, :window_start)',
        );

        $insert->execute([
            "identifier" => $key,
            "window_start" => $now,
        ]);

        return true;
    } catch (PDOException $e) {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        $isConflict = match ($driver) {
            "mysql" => $driverCode === 1062,
            "pgsql" => $e->getCode() === "23505",
            "sqlite" => $driverCode === 19 &&
                str_contains(
                    $e->getMessage(),
                    "UNIQUE constraint failed: rate_limits.identifier",
                ),
            default => false,
        };

        if (!$isConflict) {
            throw $e;
        }

        // Another request created the record first.
        $update->execute($params);

        return $update->rowCount() === 1;
    }
}

/**
 * Use the connecting IP address supplied by the server.
 *
 * Do not trust X-Forwarded-For directly. Proxy support
 * requires explicit trusted-proxy configuration.
 */
function rateLimitClientIp(): string
{
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";

    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException("Unable to identify client IP address.");
    }

    return $ip;
}
