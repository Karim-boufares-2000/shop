<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{method:string,regex:string,handler:array{0:class-string,1:string},middleware:list<MiddlewareInterface|class-string<MiddlewareInterface>>}> */
    private array $routes = [];
    /** @var list<MiddlewareInterface|class-string<MiddlewareInterface>> */
    private array $globalMiddleware = [];
    /** @var list<array{prefix:string,middleware:list<MiddlewareInterface|class-string<MiddlewareInterface>>}> */
    private array $groups = [];

    public function __construct(private Container $container)
    {
    }

    /** @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware */
    public function addGlobalMiddleware(MiddlewareInterface|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     * @param callable(Router):void $callback
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $this->groups[] = ['prefix' => $prefix, 'middleware' => $middleware];
        $callback($this);
        array_pop($this->groups);
    }

    /** @throws HttpException 404 si aucune route, 405 si le chemin existe pour une autre méthode */
    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            /** @var array<string,string> $params */
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $core = fn (Request $r): Response => $this->invoke($route['handler'], $r, $params);

            return $this->runPipeline(array_merge($this->globalMiddleware, $route['middleware']), $request, $core);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'method_not_allowed', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404, 'not_found');
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     */
    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groups as $group) {
            $prefix .= '/' . trim($group['prefix'], '/');
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }
        $full = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $full = preg_replace('#/{2,}#', '/', $full) ?? '/';

        $this->routes[] = [
            'method' => $method,
            'regex' => $this->compile($full),
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];
    }

    private function compile(string $path): string
    {
        $parts = preg_split('#(\{[a-zA-Z_][a-zA-Z0-9_]*(?::[^}]+)?\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}$#', $part, $m) === 1) {
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#u';
    }

    /**
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     * @param callable(Request):Response $core
     */
    private function runPipeline(array $middleware, Request $request, callable $core): Response
    {
        $next = $core;
        foreach (array_reverse($middleware) as $item) {
            $instance = is_string($item) ? $this->container->make($item) : $item;
            $next = fn (Request $r): Response => $instance->handle($r, $next);
        }

        return $next($request);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<string,string> $params
     */
    private function invoke(array $handler, Request $request, array $params): Response
    {
        [$class, $method] = $handler;
        $controller = $this->container->make($class);
        $response = $controller->$method($request, $params);
        if (!$response instanceof Response) {
            throw new \LogicException("{$class}::{$method} doit retourner une Response.");
        }

        return $response;
    }
}
