<?php

declare(strict_types=1);

// Composer loads this package in normal installations. The small fallback
// keeps the library usable when a module is copied next to it manually.
$composerAutoloader = __DIR__ . '/vendor/autoload.php';

if (is_file($composerAutoloader)) {
    require_once $composerAutoloader;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Hartenthaler\\Webtrees\\Shared\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
