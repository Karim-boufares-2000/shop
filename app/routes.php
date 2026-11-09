<?php

declare(strict_types=1);

use App\Controllers\Admin\AccountController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\ProductImageController;
use App\Controllers\Admin\VariantController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\LocaleController;
use App\Core\Container;
use App\Core\Router;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Services\RateLimiterInterface;

return static function (Router $router, Container $container): void {
    $limiter = $container->make(RateLimiterInterface::class);

    // Public
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/langue/{code:[a-z][a-z]}', [LocaleController::class, 'switch']);

    // Administration : connexion (hors groupe protégé)
    $router->get('/admin/login', [AuthController::class, 'showLogin']);
    $router->post('/admin/login', [AuthController::class, 'login'], [new RateLimitMiddleware($limiter, 'login', 10, 900)]);

    // Administration : zone protégée (refus par défaut)
    $router->group('/admin', [AdminAuthMiddleware::class], static function (Router $r) use ($limiter): void {
        $r->get('/', [DashboardController::class, 'index']);
        $r->post('/logout', [AuthController::class, 'logout']);
        $r->get('/password', [AccountController::class, 'form']);
        $r->post('/password', [AccountController::class, 'update'], [new RateLimitMiddleware($limiter, 'password', 10, 900)]);

        // Catalogue : catégories
        $r->get('/categories', [CategoryController::class, 'index']);
        $r->get('/categories/new', [CategoryController::class, 'create']);
        $r->post('/categories', [CategoryController::class, 'store']);
        $r->get('/categories/{id:\d+}/edit', [CategoryController::class, 'edit']);
        $r->post('/categories/{id:\d+}', [CategoryController::class, 'update']);
        $r->post('/categories/{id:\d+}/delete', [CategoryController::class, 'delete']);

        // Catalogue : produits, images, variantes
        $r->get('/products', [ProductController::class, 'index']);
        $r->get('/products/new', [ProductController::class, 'create']);
        $r->post('/products', [ProductController::class, 'store']);
        $r->get('/products/{id:\d+}/edit', [ProductController::class, 'edit']);
        $r->post('/products/{id:\d+}', [ProductController::class, 'update']);
        $r->post('/products/{id:\d+}/toggle', [ProductController::class, 'toggle']);
        $r->post('/products/{id:\d+}/delete', [ProductController::class, 'delete']);
        $r->post('/products/{id:\d+}/images', [ProductImageController::class, 'upload']);
        $r->post('/products/{id:\d+}/images/{image:\d+}/main', [ProductImageController::class, 'setMain']);
        $r->post('/products/{id:\d+}/images/{image:\d+}/delete', [ProductImageController::class, 'delete']);
        $r->post('/products/{id:\d+}/variants', [VariantController::class, 'store']);
        $r->post('/products/{id:\d+}/variants/{variant:\d+}', [VariantController::class, 'update']);
        $r->post('/products/{id:\d+}/variants/{variant:\d+}/delete', [VariantController::class, 'delete']);
    });
};
