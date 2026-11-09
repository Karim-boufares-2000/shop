<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\AuditLoggerInterface;

final class RecordingAudit implements AuditLoggerInterface
{
    /** @var list<array{action:string,admin_id:?int,meta:array<string,mixed>}> */
    public array $entries = [];

    public function log(string $action, ?int $adminId = null, ?string $entity = null, ?string $entityId = null, array $meta = [], ?string $ip = null): void
    {
        $this->entries[] = ['action' => $action, 'admin_id' => $adminId, 'meta' => $meta];
    }

    public function hashValue(string $value): string
    {
        return 'h_' . sha1($value);
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_column($this->entries, 'action');
    }
}
