<?php
declare(strict_types=1);

function jsonResponse(array $body, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($body, JSON_THROW_ON_ERROR);
}

function validationResponse(InvalidArgumentException $error): void
{
    $body = ["success" => false, "message" => $error->getMessage()];
    if ($error instanceof ValidationException) {
        $body["errors"] = $error->errors;
    }
    jsonResponse($body, 422);
}
