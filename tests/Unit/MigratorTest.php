<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testSplitsStatementsAndIgnoresComments(): void
    {
        $sql = "-- commentaire\nCREATE TABLE a (id INT);\r\n\r\n-- autre\nINSERT INTO a VALUES (1);\n";

        $this->assertSame(['CREATE TABLE a (id INT)', 'INSERT INTO a VALUES (1)'], Migrator::splitStatements($sql));
    }

    public function testSemicolonInsideLineIsNotASeparator(): void
    {
        $this->assertCount(1, Migrator::splitStatements("INSERT INTO t VALUES ('a;b');\n"));
    }

    public function testMigration002ParsesIntoTwoAlterStatements(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/002_admin_security.sql');
        $statements = Migrator::splitStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringStartsWith('ALTER TABLE admin_users', $statements[0]);
    }

    public function testMigration003CreatesFourCatalogTables(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/003_catalog.sql');
        $statements = Migrator::splitStatements($sql);

        $this->assertCount(4, $statements);
        foreach ($statements as $statement) {
            $this->assertStringStartsWith('CREATE TABLE', $statement);
        }
    }

    public function testRealMigrationFileParsesIntoFourTables(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_core_tables.sql');

        $this->assertCount(4, Migrator::splitStatements($sql));
    }
}
