<?php

declare(strict_types=1);

namespace App\Core;

/** Enveloppe PDO : connexion paresseuse, requêtes préparées natives, transactions. */
final class Database
{
    private ?\PDO $pdo = null;
    private bool $inTransaction = false;

    /** @param array<string,mixed> $config host, port, name, user, pass */
    public function __construct(private array $config)
    {
    }

    public function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 3306),
            (string) ($this->config['name'] ?? ''),
        );
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new \PDO($dsn, (string) ($this->config['user'] ?? ''), (string) ($this->config['pass'] ?? ''), $options);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'); // sans constante PDO::MYSQL_* (dépréciée en PHP 8.5)
            $pdo->exec("SET time_zone = '+00:00'"); // toutes les dates stockées sont en UTC
            $pdo->exec("SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@sql_mode, ''), 'STRICT_ALL_TABLES')");
        } catch (\PDOException $e) {
            // Message volontairement générique : le détail (hôte, utilisateur) reste dans l'exception chaînée.
            throw new \RuntimeException('Connexion à la base de données impossible.', 0, $e);
        }

        return $this->pdo = $pdo;
    }

    /** @param array<string,mixed> $params  Chaque nom de paramètre ne doit apparaître qu'une fois dans la requête. */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return array_values($this->run($sql, $params)->fetchAll());
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** @param array<string,mixed> $params  @return int lignes affectées */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function lastInsertId(): string
    {
        return (string) $this->pdo()->lastInsertId();
    }

    /**
     * Exécute $callback dans une transaction (commit si succès, rollback sinon).
     * Un appel imbriqué rejoint la transaction en cours.
     *
     * @template T
     * @param callable(self):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction) {
            return $callback($this);
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        $this->inTransaction = true;
        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->inTransaction = false;
        }
    }
}
