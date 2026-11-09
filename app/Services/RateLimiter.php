<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Limitation de fréquence en base (compatible mutualisé) : fenêtre fixe, incrément atomique. */
final class RateLimiter implements RateLimiterInterface
{
    public function __construct(private Database $db)
    {
    }

    /** @return bool true si la tentative est autorisée, false si la limite est dépassée */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $nowValue = gmdate('Y-m-d H:i:s', $now);
        $cutoff = gmdate('Y-m-d H:i:s', $now - $windowSeconds);
        $bucket = hash('sha256', $key);

        // Chaque paramètre nommé n'apparaît qu'une fois (requêtes préparées natives).
        $this->db->execute(
            'INSERT INTO rate_limits (bucket_key, hits, window_start) VALUES (:bucket, 1, :now_insert)
             ON DUPLICATE KEY UPDATE
                hits = IF(window_start <= :cutoff_hits, 1, hits + 1),
                window_start = IF(window_start <= :cutoff_start, :now_update, window_start)',
            [
                'bucket' => $bucket,
                'now_insert' => $nowValue,
                'cutoff_hits' => $cutoff,
                'cutoff_start' => $cutoff,
                'now_update' => $nowValue,
            ],
        );
        $hits = (int) $this->db->fetchValue('SELECT hits FROM rate_limits WHERE bucket_key = :bucket', ['bucket' => $bucket]);

        if (random_int(1, 100) === 1) {
            $this->db->execute('DELETE FROM rate_limits WHERE window_start < :old', ['old' => gmdate('Y-m-d H:i:s', $now - 86400)]);
        }

        return $hits <= $maxAttempts;
    }

    /** Remet le compteur à zéro (ex. après une connexion réussie). */
    public function clear(string $key): void
    {
        $this->db->execute('DELETE FROM rate_limits WHERE bucket_key = :bucket', ['bucket' => hash('sha256', $key)]);
    }
}
