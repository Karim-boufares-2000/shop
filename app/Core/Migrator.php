<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Applique les fichiers database/migrations/*.sql dans l'ordre alphabétique, une seule fois chacun.
 * Règles d'écriture : une instruction se termine par ";" en fin de ligne ; pas de procédures stockées ;
 * une migration déjà appliquée ne doit JAMAIS être modifiée (sa somme de contrôle est vérifiée).
 */
final class Migrator
{
    public function __construct(private Database $db, private string $directory)
    {
    }

    /** @return list<string> noms des migrations appliquées pendant cet appel */
    public function run(): array
    {
        $pdo = $this->db->pdo();
        if ((int) $this->db->fetchValue("SELECT GET_LOCK('shop_migrations', 10)") !== 1) {
            throw new \RuntimeException('Impossible d\'obtenir le verrou de migration.');
        }

        try {
            $this->ensureTable();
            $applied = $this->appliedChecksums();
            $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
            sort($files, SORT_STRING);

            $done = [];
            foreach ($files as $file) {
                $name = basename($file);
                $sql = str_replace("\r\n", "\n", (string) file_get_contents($file)); // insensible CRLF/LF (Windows)
                $checksum = hash('sha256', $sql);

                if (isset($applied[$name])) {
                    if (!hash_equals($applied[$name], $checksum)) {
                        throw new \RuntimeException("La migration {$name} a été modifiée après son application.");
                    }
                    continue;
                }
                foreach (self::splitStatements($sql) as $statement) {
                    $pdo->exec($statement);
                }
                $this->db->execute(
                    'INSERT INTO migrations (name, checksum) VALUES (:name, :checksum)',
                    ['name' => $name, 'checksum' => $checksum],
                );
                $done[] = $name;
            }

            return $done;
        } finally {
            $this->db->run("SELECT RELEASE_LOCK('shop_migrations')");
        }
    }

    /** @return list<string> */
    public static function splitStatements(string $sql): array
    {
        $sql = str_replace("\r\n", "\n", $sql);
        $lines = array_filter(
            explode("\n", $sql),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        );
        $parts = preg_split('/;[ \t]*(?:\n|$)/', implode("\n", $lines)) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $statement): bool => $statement !== '',
        ));
    }

    private function ensureTable(): void
    {
        $this->db->run(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_migrations_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /** @return array<string,string> */
    private function appliedChecksums(): array
    {
        $result = [];
        foreach ($this->db->fetchAll('SELECT name, checksum FROM migrations') as $row) {
            $result[(string) $row['name']] = (string) $row['checksum'];
        }

        return $result;
    }
}
