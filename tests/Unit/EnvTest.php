<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        Env::reset();
        $this->file = tempnam(sys_get_temp_dir(), 'env');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        Env::reset();
    }

    public function testParsesValuesCommentsQuotesBomAndCrlf(): void
    {
        file_put_contents($this->file, "\xEF\xBB\xBFAPP_NAME=\"Ma Boutique\"\r\n# commentaire\r\nAPP_DEBUG=true\r\nDB_PORT=3307\r\nlower=ignore\r\n");
        Env::load($this->file);

        $this->assertSame('Ma Boutique', Env::get('APP_NAME'));
        $this->assertTrue(Env::bool('APP_DEBUG'));
        $this->assertSame(3307, Env::int('DB_PORT', 3306));
        $this->assertNull(Env::get('lower'));
    }

    public function testDefaultsAreUsedWhenMissing(): void
    {
        $this->assertSame('x', Env::get('NOPE_NOT_SET', 'x'));
        $this->assertFalse(Env::bool('NOPE_NOT_SET'));
        $this->assertSame(5, Env::int('NOPE_NOT_SET', 5));
    }

    public function testNonNumericIntFallsBackToDefault(): void
    {
        Env::set('PORT_X', 'abc');
        $this->assertSame(10, Env::int('PORT_X', 10));
    }
}
