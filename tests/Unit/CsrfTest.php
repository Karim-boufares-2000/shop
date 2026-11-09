<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySession;

final class CsrfTest extends TestCase
{
    public function testTokenIsStableWithinSessionAndLongEnough(): void
    {
        $csrf = new Csrf(new ArraySession());
        $this->assertSame($csrf->token(), $csrf->token());
        $this->assertSame(64, strlen($csrf->token()));
    }

    public function testValidation(): void
    {
        $csrf = new Csrf(new ArraySession());
        $token = $csrf->token();

        $this->assertTrue($csrf->isValid($token));
        $this->assertFalse($csrf->isValid('wrong'));
        $this->assertFalse($csrf->isValid(''));
        $this->assertFalse($csrf->isValid(null));
    }

    public function testSessionWithoutTokenValidatesNothing(): void
    {
        $this->assertFalse((new Csrf(new ArraySession()))->isValid('anything'));
    }

    public function testRotateInvalidatesOldToken(): void
    {
        $csrf = new Csrf(new ArraySession());
        $old = $csrf->token();
        $csrf->rotate();

        $this->assertFalse($csrf->isValid($old));
        $this->assertNotSame($old, $csrf->token());
    }

    public function testMiddlewareBlocksPostWithoutToken(): void
    {
        $middleware = new CsrfMiddleware(new Csrf(new ArraySession()));
        $request = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/x']);

        try {
            $middleware->handle($request, fn (Request $r): Response => new Response('ok'));
            $this->fail('403 attendu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->statusCode());
            $this->assertSame('csrf', $e->getMessage());
        }
    }

    public function testMiddlewareAllowsPostWithValidTokenInBodyOrHeader(): void
    {
        $csrf = new Csrf(new ArraySession());
        $token = $csrf->token();
        $middleware = new CsrfMiddleware($csrf);
        $next = fn (Request $r): Response => new Response('ok');

        $body = new Request([], ['_csrf' => $token], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/x']);
        $header = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/x', 'HTTP_X_CSRF_TOKEN' => $token]);

        $this->assertSame('ok', $middleware->handle($body, $next)->body());
        $this->assertSame('ok', $middleware->handle($header, $next)->body());
    }

    public function testMiddlewareDoesNotRequireTokenForGet(): void
    {
        $middleware = new CsrfMiddleware(new Csrf(new ArraySession()));
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x']);

        $this->assertSame('ok', $middleware->handle($request, fn (Request $r): Response => new Response('ok'))->body());
    }
}
