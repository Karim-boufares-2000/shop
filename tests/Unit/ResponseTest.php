<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testHeaderInjectionIsNeutralized(): void
    {
        $response = Response::redirect("/ok\r\nSet-Cookie: pwned=1");

        $this->assertStringNotContainsString("\n", $response->header('Location') ?? '');
        $this->assertStringNotContainsString("\r", $response->header('Location') ?? '');
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $response = (new Response('x'))->withHeader('X-Test', 'a');
        $this->assertSame('a', $response->header('x-test'));
    }

    public function testWithHeaderDoesNotMutateOriginal(): void
    {
        $original = new Response('x');
        $original->withHeader('X-Test', 'a');
        $this->assertNull($original->header('X-Test'));
    }

    public function testWithCookieIsImmutableAndRejectsBadNames(): void
    {
        $original = new Response('x');
        $withCookie = $original->withCookie('shop_locale', 'ar', ['path' => '/']);

        $this->assertSame([], $original->cookies());
        $this->assertSame('shop_locale', $withCookie->cookies()[0]['name']);

        $this->expectException(\InvalidArgumentException::class);
        $original->withCookie("bad name;", 'x');
    }

    public function testJsonEscapesHtmlCharacters(): void
    {
        $this->assertStringNotContainsString('<', Response::json(['a' => '<script>'])->body());
    }
}
