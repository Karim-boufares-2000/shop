<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\Migrator;

/** Accès à la base de TEST (variables TEST_DB_*). Jamais la base de production. */
final class TestDatabase
{
    public static function create(): ?Database
    {
        $name = getenv('TEST_DB_NAME');
        if ($name === false || $name === '') {
            return null;
        }
        $db = new Database([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('TEST_DB_USER') ?: '',
            'pass' => getenv('TEST_DB_PASS') ?: '',
        ]);
        (new Migrator($db, dirname(__DIR__, 2) . '/database/migrations'))->run();

        return $db;
    }
}
