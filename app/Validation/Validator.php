<?php

declare(strict_types=1);

namespace App\Validation;

use App\Core\Translator;
use App\Support\Money;

/**
 * Validation côté serveur. Règles : required, nullable, string, int, numeric, min:N / max:N (longueur),
 * min_value:N / max_value:N, in:a,b, email, money, phone, regex:/motif/ — ou une Closure(string): ?string.
 */
final class Validator
{
    public function __construct(private Translator $translator, private ?PhoneNumber $phone = null)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|list<string|\Closure>> $rules
     */
    public function validate(array $data, array $rules): ValidationResult
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $definition) {
            $ruleList = is_string($definition) ? explode('|', $definition) : $definition;
            $raw = $data[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;

            if ($value === null || $value === '') {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field][] = $this->message('required');
                } else {
                    $clean[$field] = null;
                }
                continue;
            }
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                $errors[$field][] = $this->message('invalid');
                continue;
            }

            $value = (string) $value;
            $failed = false;
            foreach ($ruleList as $rule) {
                if ($rule instanceof \Closure) {
                    $error = $rule($value);
                    if (is_string($error)) {
                        $errors[$field][] = $error;
                        $failed = true;
                        break;
                    }
                    continue;
                }
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'required' || $name === 'nullable') {
                    continue;
                }
                [$error, $value] = $this->apply($name, $argument, $value);
                if ($error !== null) {
                    $errors[$field][] = $error;
                    $failed = true;
                    break;
                }
            }
            if (!$failed) {
                $clean[$field] = $value;
            }
        }

        return new ValidationResult($errors, $clean);
    }

    /** @return array{0:?string,1:mixed} [message d'erreur, valeur éventuellement transformée] */
    private function apply(string $name, ?string $argument, mixed $value): array
    {
        $text = (string) $value;

        return match ($name) {
            'string' => $this->isCleanText($text) ? [null, $value] : [$this->message('string'), $value],
            'int' => filter_var($text, FILTER_VALIDATE_INT) !== false ? [null, (int) $text] : [$this->message('int'), $value],
            'numeric' => is_numeric($text) ? [null, $value] : [$this->message('numeric'), $value],
            'min' => mb_strlen($text, 'UTF-8') >= (int) $argument ? [null, $value] : [$this->message('min', ['min' => (int) $argument]), $value],
            'max' => mb_strlen($text, 'UTF-8') <= (int) $argument ? [null, $value] : [$this->message('max', ['max' => (int) $argument]), $value],
            'min_value' => (is_numeric($text) && (float) $text >= (float) $argument) ? [null, $value] : [$this->message('min_value', ['min' => (string) $argument]), $value],
            'max_value' => (is_numeric($text) && (float) $text <= (float) $argument) ? [null, $value] : [$this->message('max_value', ['max' => (string) $argument]), $value],
            'in' => in_array($text, explode(',', (string) $argument), true) ? [null, $value] : [$this->message('in'), $value],
            'email' => filter_var($text, FILTER_VALIDATE_EMAIL) !== false ? [null, $value] : [$this->message('email'), $value],
            'regex' => preg_match((string) $argument, $text) === 1 ? [null, $value] : [$this->message('invalid'), $value],
            'money' => $this->applyMoney($text),
            'phone' => $this->applyPhone($text),
            default => throw new \InvalidArgumentException("Règle de validation inconnue : {$name}"),
        };
    }

    /** @return array{0:?string,1:mixed} */
    private function applyMoney(string $text): array
    {
        $amount = Money::parse($text);

        return $amount === null ? [$this->message('money'), $text] : [null, $amount];
    }

    /** @return array{0:?string,1:mixed} */
    private function applyPhone(string $text): array
    {
        $normalized = $this->phone?->normalize($text);

        return $normalized === null ? [$this->message('phone'), $text] : [null, $normalized];
    }

    private function isCleanText(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8')
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) !== 1;
    }

    /** @param array<string,scalar> $params */
    private function message(string $key, array $params = []): string
    {
        return $this->translator->get('validation.' . $key, $params);
    }
}
