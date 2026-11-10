<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /** @param array<string,mixed> $server */
    private function make(array $server, array $post = [], array $query = [], bool $trustProxy = false): Request
    {
        return new Request($query, $post, $server, [], [], $trustProxy);
    }

    public function testBasePathUnderXamppSubfolderWithRootRewrite(): void
    {
        $r = $this->make(['SCRIPT_NAME' => '/shop/public/index.php', 'REQUEST_URI' => '/shop/produits?x=1']);
        $this->assertSame('/shop', $r->basePath());
        $this->assertSame('/produits', $r->path());
    }

    public function testBasePathWhenAccessingPublicDirectly(): void
    {
        $r = $this->make(['SCRIPT_NAME' => '/shop/public/index.php', 'REQUEST_URI' => '/shop/public/produits']);
        $this->assertSame('/shop/public', $r->basePath());
        $this->assertSame('/produits', $r->path());
    }

    public function testBasePathAtDomainRoot(): void
    {
        $r = $this->make(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/a/b/']);
        $this->assertSame('', $r->basePath());
        $this->assertSame('/a/b', $r->path());
    }

    public function testUploadedFilesNormalizesSingleAndMultipleInputs(): void
    {
        $single = new Request([], [], [], [], ['image' => ['name' => 'a.png', 'tmp_name' => '/tmp/a', 'size' => 10, 'error' => 0]]);
        $this->assertSame([['name' => 'a.png', 'tmp_name' => '/tmp/a', 'size' => 10, 'error' => 0]], $single->uploadedFiles('image'));

        $multiple = new Request([], [], [], [], ['images' => [
            'name' => ['a.png', 'b.png'], 'tmp_name' => ['/tmp/a', '/tmp/b'], 'size' => [10, 20], 'error' => [0, 4],
        ]]);
        $files = $multiple->uploadedFiles('images');
        $this->assertCount(2, $files);
        $this->assertSame('b.png', $files[1]['name']);
        $this->assertSame(4, $files[1]['error']);

        $this->assertSame([], $multiple->uploadedFiles('absent'));
    }

    public function testDoubleLeadingSlashIsNotTreatedAsHostAndQueryIsStripped(): void
    {
        $r = $this->make(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '//a/b?x=1#f']);
        $this->assertSame('/a/b', $r->path());
    }

    public function testAbsoluteFormRequestUri(): void
    {
        $r = $this->make(['SCRIPT_NAME' => '/shop/public/index.php', 'REQUEST_URI' => 'http://localhost/shop/x']);
        $this->assertSame('/shop', $r->basePath());
        $this->assertSame('/x', $r->path());
    }

    public function testControlCharactersInPathAreRejected(): void
    {
        $r = $this->make(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/a%00b']);
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(400);
        $r->path();
    }

    public function testSafeReturnPathAcceptsInternalPath(): void
    {
        $this->assertSame('/produits?p=2', $this->make([], ['redirect' => '/produits?p=2'])->safeReturnPath('redirect'));
    }

    #[DataProvider('unsafePaths')]
    public function testSafeReturnPathRejectsOpenRedirects(string $value): void
    {
        $this->assertSame('/', $this->make([], ['redirect' => $value])->safeReturnPath('redirect'));
    }

    /** @return array<string,array{0:string}> */
    public static function unsafePaths(): array
    {
        return [
            'protocol-relative' => ['//evil.example'],
            'absolute' => ['https://evil.example'],
            'backslash' => ['/\\evil.example'],
            'backslash-inside' => ['/a\\b'],
            'newline' => ["/a\nLocation: x"],
            'no-leading-slash' => ['evil'],
        ];
    }

    public function testHostIsValidatedAndForwardedHostIgnored(): void
    {
        $this->assertSame('localhost:8080', $this->make(['HTTP_HOST' => 'localhost:8080'])->host());
        $this->assertSame('boutique.exemple.com', $this->make(['HTTP_HOST' => 'boutique.exemple.com', 'HTTP_X_FORWARDED_HOST' => 'evil.test'])->host());
        $this->assertSame('srv.local', $this->make(['HTTP_HOST' => "evil.com/../x\r\nX: y", 'SERVER_NAME' => 'srv.local'])->host());
        $this->assertSame('localhost', $this->make([])->host());
    }

    public function testQueryAllReturnsAllParameters(): void
    {
        $this->assertSame(['q' => 'a', 'page' => '2'], $this->make([], [], ['q' => 'a', 'page' => '2'])->queryAll());
    }

    public function testClientIpIgnoresForwardedForHeader(): void
    {
        $r = $this->make(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('203.0.113.5', $r->ip());
    }

    public function testIsSecureRespectsTrustedProxyFlagOnly(): void
    {
        $server = ['HTTP_X_FORWARDED_PROTO' => 'https', 'SERVER_PORT' => '80'];
        $this->assertFalse($this->make($server)->isSecure());
        $this->assertTrue($this->make($server, [], [], true)->isSecure());
        $this->assertTrue($this->make(['HTTPS' => 'on'])->isSecure());
        $this->assertFalse($this->make(['HTTPS' => 'off'])->isSecure());
    }
}
