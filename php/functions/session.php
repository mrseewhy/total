<?php
declare(strict_types=1);
function startAuthSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('total_session');
    $config=require __DIR__ . '/../auth.config.php';
    session_set_cookie_params([
        'lifetime'=>0, 'path'=>'/', 'secure'=>$config['secure_cookie'] || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly'=>true, 'samesite'=>'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store');
}
function csrfToken(): string
{
    startAuthSession();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function establishSession(array $user): void
{
    startAuthSession();
    session_regenerate_id(true);
    $_SESSION = ['user_id'=>(int)$user['id'], 'version'=>(int)$user['session_version'], 'started'=>time(), 'last_seen'=>time(), 'csrf'=>bin2hex(random_bytes(32))];
}
function endAuthSession(): void
{
    startAuthSession();
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$cookie['path'],'secure'=>$cookie['secure'],'httponly'=>true,'samesite'=>'Lax']);
    session_destroy();
}
