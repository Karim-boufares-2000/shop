<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'session_idle_timeout'     => Env::int('SESSION_IDLE_TIMEOUT', 1800),
    'session_absolute_timeout' => Env::int('SESSION_ABSOLUTE_TIMEOUT', 28800),
    'login_max_attempts'       => Env::int('LOGIN_MAX_ATTEMPTS', 5),
    'login_lock_minutes'       => Env::int('LOGIN_LOCK_MINUTES', 15),
    'trust_proxy_proto'        => Env::bool('TRUST_PROXY_PROTO', false),
];
