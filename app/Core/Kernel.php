<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\Admin\AccountController;
use App\Controllers\Admin\AdminPage;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\ProductImageController;
use App\Controllers\Admin\VariantController;
use App\Controllers\Front\CatalogController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\LocaleController;
use App\Controllers\Front\ProductPageController;
use App\Controllers\Front\SeoController;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminUserRepository;
use App\Repositories\AdminUserRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StorefrontRepository;
use App\Repositories\VariantRepository;
use App\Services\AuditLogger;
use App\Services\AuditLoggerInterface;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\ImageProcessor;
use App\Services\PasswordHasher;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\RateLimiter;
use App\Services\RateLimiterInterface;
use App\Services\SeoService;
use App\Services\StorefrontService;
use App\Services\VariantService;
use App\Validation\PhoneNumber;
use App\Validation\Validator;

/** Assemble l'application : configuration, services, gestion d'erreurs, routage, en-têtes. */
final class Kernel
{
    private function __construct(
        private Container $container,
        private ErrorHandler $errors,
    ) {
    }

    /** @param array<string,string> $envOverrides valeurs prioritaires sur .env (utilisé par les tests : base de test, APP_URL) */
    public static function boot(string $basePath, array $envOverrides = []): self
    {
        Env::load($basePath . '/.env');
        foreach ($envOverrides as $key => $value) {
            Env::set($key, $value);
        }
        $config = Config::fromDirectory($basePath . '/app/Config');

        date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $basePath . '/storage/logs/php-error.log');
        header_remove('X-Powered-By');

        $debug = (bool) $config->get('app.debug', false) && $config->get('app.env') !== 'production';
        $logger = new Logger($basePath . '/storage/logs', (string) $config->get('app.log_level', 'info'));
        $errors = new ErrorHandler($logger, $debug, $basePath . '/app/Views/errors');
        $errors->register();

        $container = new Container();
        self::registerServices($container, $config, $logger, $basePath);

        return new self($container, $errors);
    }

    public function run(): void
    {
        $config = $this->container->make(Config::class);
        $request = Request::fromGlobals((bool) $config->get('security.trust_proxy_proto', false));
        $this->handle($request)->send();
    }

    public function handle(Request $request): Response
    {
        $this->container->instance(Request::class, $request);

        try {
            // La session est paresseuse : elle ne démarre que si une écriture ou un cookie existant l'exige.
            $response = $this->container->make(Router::class)->dispatch($request);
        } catch (\Throwable $e) {
            $response = $this->errors->render($e);
        }

        return (new SecurityHeaders())->apply($request, $response);
    }

    private static function registerServices(Container $c, Config $config, Logger $logger, string $basePath): void
    {
        $c->instance(Config::class, $config);
        $c->instance(Logger::class, $logger);

        $c->singleton(Database::class, static fn (): Database => new Database((array) $config->get('database', [])));

        $c->singleton(NativeSession::class, static function (Container $c) use ($config, $basePath): NativeSession {
            $request = $c->make(Request::class);

            return new NativeSession(
                $basePath . '/storage/sessions',
                $request->isSecure(),
                (int) $config->get('security.session_idle_timeout', 1800),
                (int) $config->get('security.session_absolute_timeout', 28800),
                $request->basePath() !== '' ? $request->basePath() : '/',
            );
        });
        $c->singleton(SessionInterface::class, static fn (Container $c): SessionInterface => $c->make(NativeSession::class));
        $c->singleton(Csrf::class, static fn (Container $c): Csrf => new Csrf($c->make(SessionInterface::class)));

        $c->singleton(Translator::class, static function (Container $c) use ($config, $basePath): Translator {
            $locale = $c->make(Request::class)->cookie(LocaleController::COOKIE);
            if ($locale === null || !Translator::isSupported($locale)) {
                $locale = (string) $config->get('app.locale', 'fr');
                if (!Translator::isSupported($locale)) {
                    $locale = 'fr';
                }
            }

            return new Translator($basePath . '/lang', $locale);
        });

        $c->singleton(UrlGenerator::class, static function (Container $c) use ($config): UrlGenerator {
            $request = $c->make(Request::class);
            // URL publique : APP_URL si elle est valide, sinon dérivée de la requête (hôte validé).
            $configured = rtrim((string) $config->get('app.url', ''), '/');
            $valid = $configured !== '' && preg_match('#^https?://[A-Za-z0-9.-]+(:\d{1,5})?(/[A-Za-z0-9._~/-]*)?$#', $configured) === 1;
            $siteUrl = $valid ? $configured : ($request->isSecure() ? 'https' : 'http') . '://' . $request->host() . $request->basePath();

            return new UrlGenerator($request->basePath(), $siteUrl);
        });
        $c->singleton(View::class, static function (Container $c) use ($basePath): View {
            $view = new View(
                $basePath . '/app/Views',
                $c->make(UrlGenerator::class),
                $c->make(Translator::class),
                $c->make(Csrf::class),
                $c->make(Request::class),
            );
            // Catégories du menu public : lues seulement si le gabarit les demande.
            $view->share('navCategories', static fn (): array => $c->make(StorefrontService::class)->categoryTree());

            return $view;
        });

        $c->singleton(PhoneNumber::class, static fn (): PhoneNumber => new PhoneNumber(
            (string) $config->get('market.dial_code', '213'),
            (array) $config->get('market.phone_patterns', []),
        ));
        $c->singleton(Validator::class, static fn (Container $c): Validator => new Validator($c->make(Translator::class), $c->make(PhoneNumber::class)));

        $c->singleton(RateLimiterInterface::class, static fn (Container $c): RateLimiterInterface => new RateLimiter($c->make(Database::class)));
        $c->singleton(AuditLoggerInterface::class, static fn (Container $c): AuditLoggerInterface => new AuditLogger(
            $c->make(Database::class),
            $c->make(Logger::class),
            (string) $config->get('app.key', ''),
        ));
        $c->singleton(AdminUserRepositoryInterface::class, static fn (Container $c): AdminUserRepositoryInterface => new AdminUserRepository($c->make(Database::class)));
        $c->singleton(PasswordHasher::class, static fn (): PasswordHasher => new PasswordHasher());
        $c->singleton(AuthService::class, static fn (Container $c): AuthService => new AuthService(
            $c->make(AdminUserRepositoryInterface::class),
            $c->make(SessionInterface::class),
            $c->make(Csrf::class),
            $c->make(RateLimiterInterface::class),
            $c->make(AuditLoggerInterface::class),
            $c->make(PasswordHasher::class),
            (int) $config->get('security.login_max_attempts', 5),
            (int) $config->get('security.login_lock_minutes', 15),
        ));

        $c->bind(CsrfMiddleware::class, static fn (Container $c): CsrfMiddleware => new CsrfMiddleware($c->make(Csrf::class)));
        $c->bind(AdminAuthMiddleware::class, static fn (Container $c): AdminAuthMiddleware => new AdminAuthMiddleware(
            $c->make(AuthService::class),
            $c->make(UrlGenerator::class),
        ));

        $c->singleton(StorefrontRepository::class, static fn (Container $c): StorefrontRepository => new StorefrontRepository($c->make(Database::class)));
        $c->singleton(StorefrontService::class, static fn (Container $c): StorefrontService => new StorefrontService($c->make(StorefrontRepository::class)));
        $c->singleton(SeoService::class, static fn (): SeoService => new SeoService((string) $config->get('market.currency_code', 'DZD')));
        $c->bind(HomeController::class, static fn (Container $c): HomeController => new HomeController(
            $c->make(View::class),
            $c->make(StorefrontService::class),
            $c->make(UrlGenerator::class),
        ));
        $c->bind(CatalogController::class, static fn (Container $c): CatalogController => new CatalogController(
            $c->make(View::class),
            $c->make(StorefrontService::class),
            $c->make(SeoService::class),
            $c->make(UrlGenerator::class),
        ));
        $c->bind(ProductPageController::class, static fn (Container $c): ProductPageController => new ProductPageController(
            $c->make(View::class),
            $c->make(StorefrontService::class),
            $c->make(SeoService::class),
            $c->make(UrlGenerator::class),
        ));
        $c->bind(SeoController::class, static fn (Container $c): SeoController => new SeoController(
            $c->make(StorefrontService::class),
            $c->make(UrlGenerator::class),
        ));
        $c->bind(LocaleController::class, static fn (Container $c): LocaleController => new LocaleController($c->make(UrlGenerator::class)));
        $c->bind(AuthController::class, static fn (Container $c): AuthController => new AuthController(
            $c->make(View::class),
            $c->make(AuthService::class),
            $c->make(UrlGenerator::class),
        ));
        $c->singleton(AdminPage::class, static fn (Container $c): AdminPage => new AdminPage(
            $c->make(View::class),
            $c->make(AuthService::class),
            $c->make(SessionInterface::class),
            $c->make(UrlGenerator::class),
        ));
        $c->bind(DashboardController::class, static fn (Container $c): DashboardController => new DashboardController($c->make(AdminPage::class)));

        // Catalogue
        $c->singleton(CategoryRepository::class, static fn (Container $c): CategoryRepository => new CategoryRepository($c->make(Database::class)));
        $c->singleton(ProductRepository::class, static fn (Container $c): ProductRepository => new ProductRepository($c->make(Database::class)));
        $c->singleton(ProductImageRepository::class, static fn (Container $c): ProductImageRepository => new ProductImageRepository($c->make(Database::class)));
        $c->singleton(VariantRepository::class, static fn (Container $c): VariantRepository => new VariantRepository($c->make(Database::class)));
        $c->singleton(ImageProcessor::class, static fn (): ImageProcessor => new ImageProcessor($basePath . '/public/media/products'));
        $c->singleton(CategoryService::class, static fn (Container $c): CategoryService => new CategoryService(
            $c->make(CategoryRepository::class),
            $c->make(Validator::class),
            $c->make(Translator::class),
            $c->make(AuditLoggerInterface::class),
        ));
        $c->singleton(ProductService::class, static fn (Container $c): ProductService => new ProductService(
            $c->make(ProductRepository::class),
            $c->make(CategoryRepository::class),
            $c->make(ProductImageRepository::class),
            $c->make(ImageProcessor::class),
            $c->make(Validator::class),
            $c->make(Translator::class),
            $c->make(AuditLoggerInterface::class),
        ));
        $c->singleton(VariantService::class, static fn (Container $c): VariantService => new VariantService(
            $c->make(VariantRepository::class),
            $c->make(Validator::class),
            $c->make(Translator::class),
            $c->make(AuditLoggerInterface::class),
        ));
        $c->singleton(ProductImageService::class, static fn (Container $c): ProductImageService => new ProductImageService(
            $c->make(ProductImageRepository::class),
            $c->make(ImageProcessor::class),
            $c->make(Database::class),
            $c->make(AuditLoggerInterface::class),
        ));
        $c->bind(CategoryController::class, static fn (Container $c): CategoryController => new CategoryController($c->make(AdminPage::class), $c->make(CategoryService::class)));
        $c->bind(ProductController::class, static fn (Container $c): ProductController => new ProductController(
            $c->make(AdminPage::class),
            $c->make(ProductService::class),
            $c->make(CategoryService::class),
            $c->make(ProductImageService::class),
            $c->make(VariantService::class),
        ));
        $c->bind(ProductImageController::class, static fn (Container $c): ProductImageController => new ProductImageController(
            $c->make(AdminPage::class),
            $c->make(ProductService::class),
            $c->make(ProductImageService::class),
        ));
        $c->bind(VariantController::class, static fn (Container $c): VariantController => new VariantController(
            $c->make(AdminPage::class),
            $c->make(ProductService::class),
            $c->make(VariantService::class),
        ));
        $c->bind(AccountController::class, static fn (Container $c): AccountController => new AccountController(
            $c->make(View::class),
            $c->make(AuthService::class),
            $c->make(SessionInterface::class),
            $c->make(UrlGenerator::class),
        ));

        $c->singleton(Router::class, static function (Container $c) use ($basePath): Router {
            $router = new Router($c);
            $router->addGlobalMiddleware(CsrfMiddleware::class);
            (require $basePath . '/app/routes.php')($router, $c);

            return $router;
        });
    }
}
