<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (!is_array($config)) {
    throw new RuntimeException('Invalid database configuration.');
}

$type = strtolower(trim($config['db_type'] ?? 'mysql'));

if (!in_array($type, ['mysql', 'pgsql', 'sqlite'], true)) {
    throw new InvalidArgumentException(
        "Unsupported database type: {$type}"
    );
}

// Validate required configuration
$database = $config['database'] ?? null;

if (!is_string($database) || trim($database) === '') {
    throw new InvalidArgumentException(
        'Database name or SQLite path is required.'
    );
}

if ($type !== 'sqlite') {
    foreach (['host', 'username'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key])) {
            throw new InvalidArgumentException(
                "Missing or invalid database configuration: {$key}"
            );
        }
    }
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$port = $config['port'] ?? match ($type) {
    'mysql' => 3306,
    'pgsql' => 5432,
    'sqlite' => null,
};

if ($type !== 'sqlite') {
    if (
        filter_var($port, FILTER_VALIDATE_INT) === false ||
        (int) $port < 1 ||
        (int) $port > 65535
    ) {
        throw new InvalidArgumentException('Invalid database port.');
    }
}

switch ($type) {
    case 'mysql':
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $port,
            $database
        );
        break;

    case 'pgsql':
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'],
            $port,
            $database
        );
        break;

    case 'sqlite':
        $path = $database;

        if ($path !== ':memory:') {
            if (!str_starts_with($path, '/')) {
                $path = __DIR__ . '/' . $path;
            }

            $publicPath = realpath(__DIR__ . '/../public');
            $databaseDirectory = realpath(dirname($path));

            if ($databaseDirectory === false) {
                throw new RuntimeException(
                    'SQLite database directory does not exist.'
                );
            }

            if (
                $publicPath !== false &&
                (
                    $databaseDirectory === $publicPath ||
                    str_starts_with(
                        $databaseDirectory,
                        $publicPath . DIRECTORY_SEPARATOR
                    )
                )
            ) {
                throw new RuntimeException(
                    'SQLite database cannot be stored inside public/.'
                );
            }

            $path = $databaseDirectory . '/' . basename($path);
        }

        $dsn = 'sqlite:' . $path;
        break;
}

try {
    $db = new PDO(
        $dsn,
        $type === 'sqlite' ? null : $config['username'],
        $type === 'sqlite' ? null : ($config['password'] ?? ''),
        $options
    );

    if ($type === 'sqlite') {
        $db->exec('PRAGMA foreign_keys = ON');
    } elseif ($type === 'mysql') {
        $db->exec("SET time_zone = '+00:00'");
    } elseif ($type === 'pgsql') {
        $db->exec("SET TIME ZONE 'UTC'");
    }

    return $db;
} catch (PDOException $e) {
    error_log($e->getMessage());

    throw new RuntimeException(
        'Database connection failed.',
        0,
        $e
    );
}
