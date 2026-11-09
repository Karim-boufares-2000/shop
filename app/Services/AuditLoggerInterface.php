<?php

declare(strict_types=1);

namespace App\Services;

interface AuditLoggerInterface
{
    /** @param array<string,mixed> $meta  Jamais de mot de passe ni de donnée personnelle inutile. */
    public function log(
        string $action,
        ?int $adminId = null,
        ?string $entity = null,
        ?string $entityId = null,
        array $meta = [],
        ?string $ip = null,
    ): void;

    /** Empreinte non réversible d'une valeur (ex. nom d'utilisateur tenté) pour le journal. */
    public function hashValue(string $value): string;
}
