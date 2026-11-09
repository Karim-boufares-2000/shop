<?php

declare(strict_types=1);

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require_once $vendor;
}
require_once dirname(__DIR__) . '/app/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Tests\\')) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
