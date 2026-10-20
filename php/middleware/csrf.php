<?php
declare(strict_types=1);
require_once __DIR__ . '/../functions/session.php';
require_once __DIR__ . '/../http/response.php';
function verifyCsrf(): bool
{
    startAuthSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        jsonResponse(['success'=>false,'message'=>'Your session changed. Refresh the page and try again.'],419);
        return false;
    }
    return true;
}
