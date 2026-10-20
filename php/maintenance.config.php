<?php
declare(strict_types=1);
$config = [
    // Zero keeps activity indefinitely. Only cleanup --apply deletes records.
    "activity_days" => 90,
    // Keep limiter rows longer than the maximum supported limiter window (one day).
    "rate_limit_days" => 2,
    "batch_size" => 500,
    "max_rows_per_table" => 10000,
];
$local = __DIR__ . "/maintenance.config.local.php";
return is_file($local) ? array_replace($config, require $local) : $config;
