<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shoplog_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testSensitiveAndPersonalKeysAreRedacted(): void
    {
        $logger = new Logger($this->dir, 'debug');
        $logger->error('boom', [
            'password' => 'S3cret!',
            'phone' => '0555123456',
            'nested' => ['token' => 'abc', 'ok' => 'visible'],
        ]);

        $content = (string) file_get_contents((glob($this->dir . '/*.log') ?: [''])[0]);

        $this->assertStringNotContainsString('S3cret!', $content);
        $this->assertStringNotContainsString('0555123456', $content);
        $this->assertStringNotContainsString('abc', $content);
        $this->assertStringContainsString('visible', $content);
    }

    public function testMinimumLevelFiltersMessages(): void
    {
        $logger = new Logger($this->dir, 'error');
        $logger->info('ignored');

        $this->assertSame([], glob($this->dir . '/*.log') ?: []);
    }

    public function testNewlinesCannotForgeLogLines(): void
    {
        $logger = new Logger($this->dir, 'debug');
        $logger->info("line1\nFAKE ENTRY");

        $content = (string) file_get_contents((glob($this->dir . '/*.log') ?: [''])[0]);
        $this->assertSame(1, substr_count($content, "\n"));
    }
}
