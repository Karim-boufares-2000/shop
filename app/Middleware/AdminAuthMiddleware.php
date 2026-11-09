<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\UrlGenerator;
use App\Services\AuthService;

/** Protège les routes /admin : accès refusé par défaut, pages jamais mises en cache ni indexées. */
final class AdminAuthMiddleware implements MiddlewareInterface
{
    private const ALLOWED_WHEN_PASSWORD_CHANGE_REQUIRED = ['/admin/password', '/admin/logout'];

    public function __construct(private AuthService $auth, private UrlGenerator $urls)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $admin = $this->auth->currentAdmin();
        if ($admin === null) {
            return Response::redirect($this->urls->to('/admin/login'), 303)->withHeader('Cache-Control', 'no-store');
        }
        if ($admin['must_change_password'] === true && !in_array($request->path(), self::ALLOWED_WHEN_PASSWORD_CHANGE_REQUIRED, true)) {
            return Response::redirect($this->urls->to('/admin/password'), 303)->withHeader('Cache-Control', 'no-store');
        }

        return $next($request)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
