<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

\App\Core\Kernel::boot(dirname(__DIR__))->run();
