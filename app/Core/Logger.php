<?php

declare(strict_types=1);

namespace App\Core;

/** Journal fichier (JSON par ligne) avec masquage des champs sensibles et des données personnelles. */
final class Logger
{
    private const LEVELS = ['debug' => 100, 'info' => 200, 'warning' => 300, 'error' => 400];
    private const REDACTED_KEYS = [
        'password', 'pass', 'token', 'secret', 'csrf', '_csrf', 'authorization', 'cookie',
        'phone', 'address', 'first_name', 'last_name', 'email', 'note', 'customer_note',
    ];

    public function __construct(private string $directory, private string $minLevel = 'info')
    {
    }

    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function redact(array $context): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $result[$key] = '[masqué]';
            } elseif (is_array($value)) {
                $result[$key] = $this->redact($value);
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = is_string($value) ? $this->clean($value) : $value;
            } else {
                $result[$key] = '[' . get_debug_type($value) . ']';
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $min = self::LEVELS[$this->minLevel] ?? self::LEVELS['info'];
        if (self::LEVELS[$level] < $min) {
            return;
        }
        $line = json_encode([
            'time' => date('c'),
            'level' => $level,
            'message' => $this->clean($message),
            'context' => $this->redact($context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $line = ($line === false ? '{"level":"error","message":"log encoding failed"}' : $line) . PHP_EOL;

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
        $file = rtrim($this->directory, '/') . '/app-' . date('Y-m-d') . '.log';
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(rtrim($line));
        }
    }

    private function clean(string $value): string
    {
        return mb_substr(str_replace(["\r", "\n"], ' ', $value), 0, 1000);
    }
}
