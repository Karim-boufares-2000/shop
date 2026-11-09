<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Repositories\AdminUserRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/** Base de TEST uniquement (variable TEST_DB_NAME). */
final class AdminUserRepositoryTest extends TestCase
{
    private Database $db;
    private AdminUserRepository $repo;

    protected function setUp(): void
    {
        $db = TestDatabase::create();
        if ($db === null) {
            $this->markTestSkipped('TEST_DB_NAME non défini : test d\'intégration ignoré.');
        }
        $this->db = $db;
        $this->db->execute('DELETE FROM audit_log');
        $this->db->execute('DELETE FROM admin_users');
        $this->repo = new AdminUserRepository($this->db);
    }

    public function testCreateAndFindWithTypedFields(): void
    {
        $id = $this->repo->create('Admin', 'hash123', true);
        $row = $this->repo->findByUsername('admin'); // interclassement insensible à la casse

        $this->assertNotNull($row);
        $this->assertSame($id, $row['id']);
        $this->assertTrue($row['must_change_password']);
        $this->assertSame(0, $row['failed_attempts']);
        $this->assertNull($row['locked_until']);
    }

    public function testUsernameIsUnique(): void
    {
        $this->repo->create('admin', 'h', false);

        $this->expectException(\PDOException::class);
        $this->repo->create('ADMIN', 'h', false);
    }

    public function testRecordFailureLocksExactlyAtThreshold(): void
    {
        $id = $this->repo->create('admin', 'h', false);
        $until = gmdate('Y-m-d H:i:s', time() + 900);

        for ($i = 1; $i <= 4; $i++) {
            $this->repo->recordFailure($id, 5, $until);
        }
        $this->assertNull($this->repo->findById($id)['locked_until']);

        $this->repo->recordFailure($id, 5, $until);
        $row = $this->repo->findById($id);
        $this->assertSame($until, $row['locked_until']);
        $this->assertSame(5, $row['failed_attempts']);
    }

    public function testRecordSuccessAndUnlockResetCounters(): void
    {
        $id = $this->repo->create('admin', 'h', false);
        $this->repo->recordFailure($id, 1, gmdate('Y-m-d H:i:s', time() + 900));
        $this->repo->recordSuccess($id, gmdate('Y-m-d H:i:s'));
        $row = $this->repo->findById($id);
        $this->assertSame(0, $row['failed_attempts']);
        $this->assertNull($row['locked_until']);

        $this->repo->recordFailure($id, 1, gmdate('Y-m-d H:i:s', time() + 900));
        $this->repo->unlock($id);
        $this->assertNull($this->repo->findById($id)['locked_until']);
    }

    public function testUpdatePasswordClearsMustChangeFlag(): void
    {
        $id = $this->repo->create('admin', 'old', true);
        $this->repo->updatePassword($id, 'new', false, gmdate('Y-m-d H:i:s'));
        $row = $this->repo->findById($id);

        $this->assertSame('new', $row['password_hash']);
        $this->assertFalse($row['must_change_password']);
    }

    public function testSqlInjectionAttemptInUsernameIsHarmless(): void
    {
        $this->repo->create('admin', 'h', false);

        $this->assertNull($this->repo->findByUsername("admin' OR '1'='1"));
        $this->assertNotNull($this->repo->findByUsername('admin'));
    }
}
