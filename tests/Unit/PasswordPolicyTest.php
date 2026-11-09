<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    #[DataProvider('cases')]
    public function testPolicy(string $password, string $username, ?string $expected): void
    {
        $this->assertSame($expected, PasswordPolicy::check($password, $username));
    }

    /** @return array<string,array{0:string,1:string,2:?string}> */
    public static function cases(): array
    {
        return [
            'valide' => ['correct horse battery', 'admin', null],
            'valide arabe' => ['كلمةمرورطويلةجدا١٢٣', 'admin', null],
            'trop court' => ['Abc123!', 'admin', 'password.too_short'],
            'onze caractères' => ['abcdefghijk', 'admin', 'password.too_short'],
            'trop long (>72 octets)' => [str_repeat('ab1', 30), 'admin', 'password.too_long'],
            'contient le nom' => ['my-Administrator-2026', 'administrator', 'password.contains_username'],
            'trop simple' => ['aaaaaaaaaaaaaaaa', 'admin', 'password.too_simple'],
            'alternance simple' => ['abababababababab', 'admin', 'password.too_simple'],
        ];
    }
}
