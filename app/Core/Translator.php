<?php

declare(strict_types=1);

namespace App\Core;

final class Translator
{
    public const SUPPORTED = ['fr', 'ar'];

    /** @var array<string,array<string,string>> */
    private array $messages = [];

    public function __construct(private string $langPath, private string $locale, private string $fallback = 'fr')
    {
        if (!self::isSupported($locale) || !self::isSupported($fallback)) {
            throw new \InvalidArgumentException('Langue non prise en charge.');
        }
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function direction(): string
    {
        return $this->locale === 'ar' ? 'rtl' : 'ltr';
    }

    /** @param array<string,scalar> $params  Remplace ":nom" par la valeur. */
    public function get(string $key, array $params = []): string
    {
        $text = $this->load($this->locale)[$key] ?? $this->load($this->fallback)[$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }

    /** @return array<string,string> */
    private function load(string $locale): array
    {
        if (!isset($this->messages[$locale])) {
            $file = rtrim($this->langPath, '/') . '/' . $locale . '.php';
            $loaded = is_file($file) ? require $file : [];
            $this->messages[$locale] = is_array($loaded) ? $loaded : [];
        }

        return $this->messages[$locale];
    }
}
