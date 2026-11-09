<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Migrator;
use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Nécessite une base MySQL/MariaDB DÉDIÉE AUX TESTS (jamais la base de production) :
 * TEST_DB_HOST, TEST_DB_PORT (opt.), TEST_DB_NAME, TEST_DB_USER, TEST_DB_PASS.
 */
final class RateLimiterTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $name = getenv('TEST_DB_NAME');
        if ($name === false || $name === '') {
            $this->markTestSkipped('TEST_DB_NAME non défini : test d\'intégration ignoré.');
        }
        $this->db = new Database([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('TEST_DB_USER') ?: '',
            'pass' => getenv('TEST_DB_PASS') ?: '',
        ]);
        (new Migrator($this->db, dirname(__DIR__, 2) . '/database/migrations'))->run();
        $this->db->execute('DELETE FROM rate_limits');
    }

    public function testBlocksAfterMaxAttempts(): void
    {
        $limiter = new RateLimiter($this->db);

        $this->assertTrue($limiter->attempt('login|1.2.3.4', 3, 60));
        $this->assertTrue($limiter->attempt('login|1.2.3.4', 3, 60));
        $this->assertTrue($limiter->attempt('login|1.2.3.4', 3, 60));
        $this->assertFalse($limiter->attempt('login|1.2.3.4', 3, 60));
    }

    public function testBucketsAreIndependent(): void
    {
        $limiter = new RateLimiter($this->db);
        $limiter->attempt('a', 1, 60);

        $this->assertFalse($limiter->attempt('a', 1, 60));
        $this->assertTrue($limiter->attempt('b', 1, 60));
    }

    public function testClearResetsCounter(): void
    {
        $limiter = new RateLimiter($this->db);
        $limiter->attempt('k', 1, 60);
        $limiter->clear('k');

        $this->assertTrue($limiter->attempt('k', 1, 60));
    }

    public function testMigrationsAreIdempotent(): void
    {
        $migrator = new Migrator($this->db, dirname(__DIR__, 2) . '/database/migrations');

        $this->assertSame([], $migrator->run());
    }
}
