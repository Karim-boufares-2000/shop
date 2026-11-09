<?php

declare(strict_types=1);

namespace App\Services;

/** Refus de téléversement ; porte une clé de traduction affichable à l'administrateur. */
final class UploadException extends \RuntimeException
{
    public function __construct(private string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public function key(): string
    {
        return $this->translationKey;
    }
}
