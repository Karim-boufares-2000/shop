<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class RecordingMiddleware implements MiddlewareInterface
{
    public function __construct(private \ArrayObject $log, private string $label)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $this->log[] = $this->label;

        return $next($request);
    }
}
