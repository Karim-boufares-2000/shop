<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Kernel;
use App\Core\Request;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Parcours HTTP complet sans base de données ni serveur web.
 * Processus séparés : la session PHP et les gestionnaires d'erreurs sont globaux.
 * Aucune de ces requêtes ne doit atteindre la base (session paresseuse, CSRF avant tout accès SQL).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class KernelTest extends TestCase
{
    private function kernel(): Kernel
    {
        return Kernel::boot(dirname(__DIR__, 2));
    }

    /** @param array<string,mixed> $post @param array<string,mixed> $cookies */
    private function request(string $method, string $uri, array $post = [], array $cookies = []): Request
    {
        return new Request([], $post, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php'], $cookies);
    }

    public function testLoginPageRendersLanguageLinksAndSecurityHeaders(): void
    {
        $response = $this->kernel()->handle($this->request('GET', '/admin/login'));

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('<html lang="fr" dir="ltr">', $response->body());
        $this->assertStringContainsString('/langue/ar', $response->body());
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertNotNull($response->header('Content-Security-Policy'));
    }

    public function testLanguageSwitchSetsCookieAndRedirectsSafely(): void
    {
        $response = $this->kernel()->handle(new Request(['redirect' => '/produits'], [], [
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/langue/ar?redirect=%2Fproduits', 'SCRIPT_NAME' => '/index.php',
        ]));

        $this->assertSame(303, $response->status());
        $this->assertSame('/produits', $response->header('Location'));
        $this->assertSame('shop_locale', $response->cookies()[0]['name']);
        $this->assertSame('ar', $response->cookies()[0]['value']);
        $this->assertTrue($response->cookies()[0]['options']['httponly']);
    }

    public function testLanguageSwitchIgnoresExternalRedirect(): void
    {
        $response = $this->kernel()->handle(new Request(['redirect' => '//evil.example'], [], [
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/langue/fr', 'SCRIPT_NAME' => '/index.php',
        ]));

        $this->assertSame('/', $response->header('Location'));
    }

    public function testUnsupportedLanguageIs404(): void
    {
        $this->assertSame(404, $this->kernel()->handle($this->request('GET', '/langue/zz'))->status());
    }

    public function testArabicCookieSwitchesDirection(): void
    {
        $response = $this->kernel()->handle($this->request('GET', '/admin/login', [], ['shop_locale' => 'ar']));

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $response->body());
    }

    public function testInvalidLocaleCookieFallsBackToDefault(): void
    {
        $response = $this->kernel()->handle($this->request('GET', '/admin/login', [], ['shop_locale' => '../../etc']));

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('lang="fr"', $response->body());
    }

    public function testUnknownRouteGives404Page(): void
    {
        $response = $this->kernel()->handle($this->request('GET', '/inexistant'));

        $this->assertSame(404, $response->status());
        $this->assertNotNull($response->header('Content-Security-Policy'));
    }

    public function testErrorPagesDoNotLeakInternalPaths(): void
    {
        $response = $this->kernel()->handle($this->request('GET', '/inexistant'));

        $this->assertStringNotContainsString('Stack trace', $response->body());
        $this->assertStringNotContainsString('C:\\', $response->body());
    }

    public function testAdminAreaRedirectsAnonymousVisitorsToLogin(): void
    {
        foreach (['/admin', '/admin/password'] as $path) {
            $response = $this->kernel()->handle($this->request('GET', $path));

            $this->assertSame(303, $response->status(), $path);
            $this->assertSame('/admin/login', $response->header('Location'), $path);
            $this->assertSame('no-store', $response->header('Cache-Control'), $path);
        }
    }

    public function testLoginPageRendersFormWithCsrfAndNoIndex(): void
    {
        $response = $this->kernel()->handle($this->request('GET', '/admin/login'));

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('name="_csrf"', $response->body());
        $this->assertStringContainsString('autocomplete="current-password"', $response->body());
        $this->assertStringContainsString('noindex', $response->body());
    }

    public function testCatalogRoutesRequireAuthentication(): void
    {
        foreach (['/admin/categories', '/admin/categories/new', '/admin/categories/1/edit', '/admin/products', '/admin/products/new', '/admin/products/1/edit'] as $path) {
            $response = $this->kernel()->handle($this->request('GET', $path));

            $this->assertSame(303, $response->status(), $path);
            $this->assertSame('/admin/login', $response->header('Location'), $path);
        }
    }

    public function testCatalogMutationsWithoutCsrfAreForbidden(): void
    {
        $paths = [
            '/admin/categories', '/admin/categories/1', '/admin/categories/1/delete',
            '/admin/products', '/admin/products/1', '/admin/products/1/toggle', '/admin/products/1/delete',
            '/admin/products/1/images', '/admin/products/1/images/1/main', '/admin/products/1/images/1/delete',
            '/admin/products/1/variants', '/admin/products/1/variants/1', '/admin/products/1/variants/1/delete',
        ];
        foreach ($paths as $path) {
            $this->assertSame(403, $this->kernel()->handle($this->request('POST', $path, ['name_fr' => 'x']))->status(), $path);
        }
    }

    public function testNonNumericIdsAreNotRouted(): void
    {
        $this->assertSame(404, $this->kernel()->handle($this->request('GET', '/admin/products/abc/edit'))->status());
    }

    public function testAdminPostsWithoutCsrfAreForbidden(): void
    {
        foreach (['/admin/login', '/admin/logout', '/admin/password'] as $path) {
            $response = $this->kernel()->handle($this->request('POST', $path, ['username' => 'admin', 'password' => 'x']));

            $this->assertSame(403, $response->status(), $path);
        }
    }
}
