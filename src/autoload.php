<?php

declare(strict_types=1);

$prefix = 'Hartenthaler\\Webtrees\\Module\\GeMeDaModule\\';
$base = __DIR__ . '/';

spl_autoload_register(static function (string $class) use ($prefix, $base): void {
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
