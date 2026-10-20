<?php
declare(strict_types=1);
final class HttpRequestException extends RuntimeException {}
function jsonRequest(): array
{
    $type = strtolower(trim(explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0]));
    if ($type !== "application/json") {
        throw new HttpRequestException(
            "Send this request as application/json.",
            415,
        );
    }
    // Content allows 1 MB of blocks plus bounded metadata; never read an unbounded body.
    $limit = 1100000;
    if (
        isset($_SERVER["CONTENT_LENGTH"]) &&
        (int) $_SERVER["CONTENT_LENGTH"] > $limit
    ) {
        throw new HttpRequestException("Request body is too large.", 413);
    }
    $body = file_get_contents("php://input", false, null, 0, $limit + 1);
    if ($body === false) {
        throw new InvalidArgumentException("Could not read request.");
    }
    if (strlen($body) > $limit) {
        throw new HttpRequestException("Request body is too large.", 413);
    }
    try {
        $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException("Invalid JSON request.", 0, $e);
    }
    if (!is_array($data) || !str_starts_with(ltrim($body), "{")) {
        throw new InvalidArgumentException("A JSON object is required.");
    }
    return $data;
}
