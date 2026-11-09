<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testBaselineHeadersArePresent(): void
    {
        $response = (new SecurityHeaders())->apply(new Request(), new Response('x'));

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertStringContainsString("default-src 'self'", (string) $response->header('Content-Security-Policy'));
        $this->assertNull($response->header('Strict-Transport-Security'));
    }

    public function testDefaultCacheControlPreventsSharedCaching(): void
    {
        $response = (new SecurityHeaders())->apply(new Request(), new Response('x'));

        $this->assertSame('private, no-cache', $response->header('Cache-Control'));
    }

    public function testHstsOnlyOverHttps(): void
    {
        $request = new Request([], [], ['HTTPS' => 'on']);
        $response = (new SecurityHeaders())->apply($request, new Response('x'));

        $this->assertNotNull($response->header('Strict-Transport-Security'));
    }

    public function testExistingHeaderIsNotOverwritten(): void
    {
        $response = (new SecurityHeaders())->apply(new Request(), (new Response('x'))->withHeader('X-Frame-Options', 'SAMEORIGIN'));

        $this->assertSame('SAMEORIGIN', $response->header('X-Frame-Options'));
    }
}
