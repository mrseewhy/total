<?php
declare(strict_types=1);
require_once __DIR__ . '/../functions/session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../http/response.php';
function currentUser(): ?array
{
    startAuthSession();
    if (!isset($_SESSION['user_id'])) return null;
    if (time()-($_SESSION['last_seen'] ?? 0)>1800 || time()-($_SESSION['started'] ?? 0)>43200) { endAuthSession(); return null; }
    $db=require __DIR__ . '/../db.php';
    $user=(new User($db))->byId((int)$_SESSION['user_id']);
    if (!$user || !User::active($user) || (int)$user['session_version'] !== (int)($_SESSION['version'] ?? 0)) { endAuthSession(); return null; }
    $_SESSION['last_seen']=time();
    return $user;
}
function requireAdmin(): ?array
{
    $user=currentUser();
    if (!$user) { jsonResponse(['success'=>false,'message'=>'Please sign in.'],401); return null; }
    if ($user['role'] !== 'Admin') { jsonResponse(['success'=>false,'message'=>'Access denied.'],403); return null; }
    return $user;
}

function requireStaff(): ?array
{
    $user=currentUser();
    if(!$user) { jsonResponse(['success'=>false,'message'=>'Please sign in.'],401); return null; }
    if(!in_array($user['role'],['Admin','Manager'],true)) { jsonResponse(['success'=>false,'message'=>'Access denied.'],403); return null; }
    return $user;
}
