<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require dirname(__DIR__) . '/src/bootstrap.php';

// Tests run against a throwaway database, never the dev/demo one — the real
// DB_NAME from .env is loaded above, then overridden here.
$_ENV['DB_NAME'] = 'digipay_ghana_test';
putenv('DB_NAME=digipay_ghana_test');

\App\Support\Migrator::run(true);

