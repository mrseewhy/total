<?php
declare(strict_types=1);
ini_set("display_errors", "0");
header("X-Robots-Tag: noindex, nofollow");
header("Cache-Control: no-store");
try {
    require_once __DIR__ . "/../../php/http/security.php";
    require_once __DIR__ . "/../../php/http/response.php";
    require_once __DIR__ . "/../../php/http/request.php";
    securityHeaders();
    require_once __DIR__ . "/../../php/maintenance/Mode.php";
    if (!MaintenanceMode::enterRequest(true)) {
        return;
    }
    require_once __DIR__ . "/../../php/Router.php";
    $router = new Router();
    $registerRoutes = require __DIR__ . "/../../php/routes.php";
    $registerRoutes($router);
    $router->dispatch();
} catch (HttpRequestException $e) {
    jsonResponse(
        ["success" => false, "message" => $e->getMessage()],
        $e->getCode(),
    );
} catch (InvalidArgumentException $e) {
    validationResponse($e);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "success" => false,
        "message" => "Could not process request. Please try again.",
    ]);
}
