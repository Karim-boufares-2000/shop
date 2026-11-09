<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/** Exige un jeton CSRF valide pour toute requête qui n'est pas GET/HEAD/OPTIONS. */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::SAFE_METHODS, true)) {
            $submitted = $request->post(Csrf::FIELD) ?? $request->header('X-CSRF-Token');
            if (!$this->csrf->isValid(is_string($submitted) ? $submitted : null)) {
                throw new HttpException(403, 'csrf');
            }
        }

        return $next($request);
    }
}
