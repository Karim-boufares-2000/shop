<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\DummyController;
use Tests\Support\RecordingMiddleware;

final class RouterTest extends TestCase
{
    private function request(string $method, string $uri): Request
    {
        return new Request([], [], ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php']);
    }

    public function testMatchesStaticRoute(): void
    {
        $router = new Router(new Container());
        $router->get('/', [DummyController::class, 'index']);

        $this->assertSame('index', $router->dispatch($this->request('GET', '/'))->body());
    }

    public function testExtractsNamedParameters(): void
    {
        $router = new Router(new Container());
        $router->get('/produit/{id:\d+}', [DummyController::class, 'show']);

        $this->assertSame('id=42', $router->dispatch($this->request('GET', '/produit/42'))->body());
    }

    public function testParameterConstraintIsEnforced(): void
    {
        $router = new Router(new Container());
        $router->get('/produit/{id:\d+}', [DummyController::class, 'show']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $router->dispatch($this->request('GET', '/produit/abc'));
    }

    public function testUnknownRouteIs404(): void
    {
        $router = new Router(new Container());

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $router->dispatch($this->request('GET', '/nope'));
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $router = new Router(new Container());
        $router->get('/x', [DummyController::class, 'index']);

        try {
            $router->dispatch($this->request('POST', '/x'));
            $this->fail('405 attendu');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->statusCode());
            $this->assertSame('GET', $e->headers()['Allow']);
        }
    }

    public function testGroupPrefixApplies(): void
    {
        $router = new Router(new Container());
        $router->group('/admin', [], function (Router $r): void {
            $r->get('/dash', [DummyController::class, 'index']);
        });

        $this->assertSame('index', $router->dispatch($this->request('GET', '/admin/dash'))->body());
    }

    public function testMiddlewareRunsGlobalThenRoute(): void
    {
        $log = new \ArrayObject();
        $router = new Router(new Container());
        $router->addGlobalMiddleware(new RecordingMiddleware($log, 'global'));
        $router->get('/x', [DummyController::class, 'index'], [new RecordingMiddleware($log, 'route')]);

        $router->dispatch($this->request('GET', '/x'));

        $this->assertSame(['global', 'route'], $log->getArrayCopy());
    }

    public function testTrailingSlashAndDoubleSlashAreNormalized(): void
    {
        $router = new Router(new Container());
        $router->get('/a/b', [DummyController::class, 'index']);

        $this->assertSame('index', $router->dispatch($this->request('GET', '//a//b/'))->body());
    }
}
