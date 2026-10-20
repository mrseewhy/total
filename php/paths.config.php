<?php
declare(strict_types=1);
$config = ['public_directory' => dirname(__DIR__) . '/public'];
$local = __DIR__ . '/paths.config.local.php';
return is_file($local) ? array_replace($config, require $local) : $config;
