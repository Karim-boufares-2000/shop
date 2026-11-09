<?php

declare(strict_types=1);

namespace App\Validation;

final class ValidationResult
{
    /**
     * @param array<string,list<string>> $errors
     * @param array<string,mixed> $validated
     */
    public function __construct(private array $errors, private array $validated)
    {
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,string> premier message d'erreur de chaque champ */
    public function firstErrors(): array
    {
        return array_map(static fn (array $messages): string => (string) ($messages[0] ?? ''), $this->errors);
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /** @return array<string,mixed> valeurs nettoyées des seuls champs valides */
    public function validated(): array
    {
        return $this->validated;
    }
}
