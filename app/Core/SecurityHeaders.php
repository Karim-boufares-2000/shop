<?php

declare(strict_types=1);

namespace App\Core;

/** Ajoute les en-têtes de sécurité à toute réponse (y compris les pages d'erreur). */
final class SecurityHeaders
{
    public function apply(Request $request, Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // Par défaut : jamais de cache partagé (proxy) ; une page publique peut définir sa propre valeur.
            'Cache-Control' => 'private, no-cache',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; script-src 'self'; "
                . "style-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; "
                . "form-action 'self'; frame-ancestors 'none'",
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        foreach ($headers as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
