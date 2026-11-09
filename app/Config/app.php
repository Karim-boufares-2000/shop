<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'env'       => Env::get('APP_ENV', 'production'),
    'debug'     => Env::bool('APP_DEBUG', false),
    'url'       => Env::get('APP_URL', ''),
    'key'       => Env::get('APP_KEY', ''),
    'timezone'  => Env::get('APP_TIMEZONE', 'Africa/Algiers'),
    'locale'    => Env::get('APP_LOCALE', 'fr'),
    'log_level' => Env::get('LOG_LEVEL', 'info'),
];
