<?php

declare(strict_types=1);

namespace App\Core;

/** Convertit erreurs et exceptions en pages d'erreur sûres ; journalise ; n'affiche les détails qu'en debug. */
final class ErrorHandler
{
    /** @var array<int|string,array{0:string,1:string}> */
    private const TITLES = [
        400 => ['Requête invalide', 'طلب غير صالح'],
        403 => ['Accès refusé', 'الوصول مرفوض'],
        404 => ['Page introuvable', 'الصفحة غير موجودة'],
        405 => ['Méthode non autorisée', 'الطريقة غير مسموح بها'],
        429 => ['Trop de requêtes, réessayez plus tard', 'طلبات كثيرة جدًا، حاول لاحقًا'],
        500 => ['Erreur interne du serveur', 'خطأ داخلي في الخادم'],
        'csrf' => ['Session expirée : rechargez la page et réessayez', 'انتهت الجلسة، أعد تحميل الصفحة وحاول مجددًا'],
    ];

    public function __construct(private Logger $logger, private bool $debug, private string $viewsPath)
    {
    }

    public function register(): void
    {
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if ((error_reporting() & $level) === 0) {
                return false;
            }
            $soft = [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE];
            if (!$this->debug && in_array($level, $soft, true)) {
                $this->logger->warning('PHP: ' . $message, ['file' => basename($file), 'line' => $line]);

                return true;
            }
            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        set_exception_handler(function (\Throwable $e): void {
            $this->render($e)->send();
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->logger->error('Fatal: ' . $error['message'], ['file' => basename($error['file']), 'line' => $error['line']]);
                if (!headers_sent()) {
                    http_response_code(500);
                    echo $this->page(500, 500, null);
                }
            }
        });
    }

    public function render(\Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->statusCode() : 500;
        $titleKey = ($e instanceof HttpException && $e->getMessage() === 'csrf') ? 'csrf' : $status;

        if ($status >= 500) {
            $this->logger->error(get_class($e) . ': ' . $e->getMessage(), ['file' => basename($e->getFile()), 'line' => $e->getLine()]);
        } elseif ($status === 403 || $status === 429) {
            $this->logger->info('HTTP ' . $status, ['reason' => $e->getMessage()]);
        }

        $detail = $this->debug ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null;
        $headers = ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store'];
        if ($e instanceof HttpException) {
            $headers += $e->headers();
        }

        return new Response($this->page($status, $titleKey, $detail), $status, $headers);
    }

    private function page(int $status, int|string $titleKey, ?string $detail): string
    {
        $titles = self::TITLES[$titleKey] ?? self::TITLES[$status >= 500 ? 500 : 400];
        $file = $this->viewsPath . '/error.php';
        if (!is_file($file)) {
            return 'Error ' . $status;
        }

        ob_start();
        (static function () use ($file, $status, $titles, $detail): void {
            $titleFr = $titles[0];
            $titleAr = $titles[1];
            require $file;
        })();

        return (string) ob_get_clean();
    }
}
