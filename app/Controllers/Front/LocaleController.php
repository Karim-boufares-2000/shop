<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Translator;
use App\Core\UrlGenerator;

/** Choix de langue stocké dans un cookie de préférence (pas de session pour les visiteurs anonymes). */
final class LocaleController
{
    public const COOKIE = 'shop_locale';

    public function __construct(private UrlGenerator $urls)
    {
    }

    /** @param array<string,string> $params */
    public function switch(Request $request, array $params): Response
    {
        $code = $params['code'] ?? '';
        if (!Translator::isSupported($code)) {
            throw new HttpException(404, 'not_found');
        }

        return Response::redirect($this->urls->to($request->safeReturnPath('redirect', '/')), 303)
            ->withCookie(self::COOKIE, $code, [
                'expires' => time() + 365 * 86400,
                'path' => $request->basePath() !== '' ? $request->basePath() : '/',
                'secure' => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
    }
}
