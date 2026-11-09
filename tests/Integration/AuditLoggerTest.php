<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Logger;
use App\Services\AuditLogger;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AuditLoggerTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $db = TestDatabase::create();
        if ($db === null) {
            $this->markTestSkipped('TEST_DB_NAME non défini : test d\'intégration ignoré.');
        }
        $this->db = $db;
        $this->db->execute('DELETE FROM audit_log');
    }

    private function logger(string $key): AuditLogger
    {
        return new AuditLogger($this->db, new Logger(sys_get_temp_dir() . '/shop_audit_test_logs', 'error'), $key);
    }

    public function testWritesEntryWithHashedIpAndRedactedMeta(): void
    {
        $this->logger('secret-key')->log('auth.login', null, 'admin', '7', ['phone' => '0555123456', 'ok' => 'visible'], '203.0.113.9');
        $row = $this->db->fetchOne('SELECT * FROM audit_log LIMIT 1');

        $this->assertNotNull($row);
        $this->assertSame('auth.login', $row['action']);
        $this->assertSame(hash_hmac('sha256', '203.0.113.9', 'secret-key'), $row['ip_hash']);
        $this->assertStringNotContainsString('0555123456', (string) $row['meta_json']);
        $this->assertStringContainsString('visible', (string) $row['meta_json']);
        $this->assertStringNotContainsString('203.0.113.9', (string) json_encode($row));
    }

    public function testWithoutKeyNoIpIsStored(): void
    {
        $this->logger('')->log('auth.login', null, null, null, [], '203.0.113.9');

        $this->assertNull($this->db->fetchValue('SELECT ip_hash FROM audit_log LIMIT 1'));
    }

    public function testDatabaseFailureDoesNotThrow(): void
    {
        $broken = new AuditLogger(new Database(['host' => '127.0.0.1', 'port' => 1, 'name' => 'x', 'user' => 'x', 'pass' => '']), new Logger(sys_get_temp_dir() . '/shop_audit_test_logs', 'error'), 'k');
        $broken->log('auth.login');

        $this->addToAssertionCount(1);
    }
}
