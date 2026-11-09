<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Journal d'audit en base. Les IP et valeurs sensibles sont enregistrées sous forme d'empreinte HMAC
 * (clé APP_KEY) : sans clé, aucune IP n'est stockée. Un échec d'écriture est journalisé dans un
 * fichier mais ne bloque pas l'action (choix de disponibilité).
 */
final class AuditLogger implements AuditLoggerInterface
{
    public function __construct(private Database $db, private Logger $logger, private string $key)
    {
    }

    public function log(
        string $action,
        ?int $adminId = null,
        ?string $entity = null,
        ?string $entityId = null,
        array $meta = [],
        ?string $ip = null,
    ): void {
        try {
            $metaJson = $meta === [] ? null : json_encode($this->logger->redact($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $this->db->execute(
                'INSERT INTO audit_log (admin_id, action, entity, entity_id, meta_json, ip_hash)
                 VALUES (:admin_id, :action, :entity, :entity_id, :meta_json, :ip_hash)',
                [
                    'admin_id' => $adminId,
                    'action' => mb_substr($action, 0, 80),
                    'entity' => $entity !== null ? mb_substr($entity, 0, 50) : null,
                    'entity_id' => $entityId !== null ? mb_substr($entityId, 0, 64) : null,
                    'meta_json' => $metaJson === false ? null : $metaJson,
                    'ip_hash' => $this->hashIp($ip),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('Écriture du journal d\'audit impossible', ['exception' => get_class($e), 'action' => $action]);
        }
    }

    public function hashValue(string $value): string
    {
        return $this->key !== '' ? hash_hmac('sha256', $value, $this->key) : hash('sha256', $value);
    }

    private function hashIp(?string $ip): ?string
    {
        return ($ip === null || $this->key === '') ? null : hash_hmac('sha256', $ip, $this->key);
    }
}
