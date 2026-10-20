<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once __DIR__.'/../models/User.php';
require_once __DIR__.'/../validation/AuthValidation.php';
try {
    $name=Validator::requiredString(readline('Name: '),'Name is required.'); Validator::maxLength($name,100,'Name is too long.');
    $email=AuthValidation::email(['email'=>readline('Email: ')]);
    if(PHP_OS_FAMILY!=='Windows') system('stty -echo');
    try { $password=readline('Password (12–72 bytes): '); echo PHP_EOL; $confirmation=readline('Confirm password: '); echo PHP_EOL; }
    finally { if(PHP_OS_FAMILY!=='Windows') system('stty echo'); }
    AuthValidation::newPassword(['password'=>$password,'password_confirmation'=>$confirmation]);
    $db=require __DIR__.'/../db.php'; (new User($db))->create($name,$email,$password);
    echo "Administrator created. Sign in at /site-admin/login.\n";
} catch(Throwable $e) { fwrite(STDERR,"Could not create administrator: ".$e->getMessage()."\n"); exit(1); }
