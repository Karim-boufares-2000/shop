<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashVerifyRoundTripAndSalting(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct horse battery');

        $this->assertNotSame('correct horse battery', $hash);
        $this->assertNotSame($hash, $hasher->hash('correct horse battery')); // sel aléatoire
        $this->assertTrue($hasher->verify('correct horse battery', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
        $this->assertFalse($hasher->needsRehash($hash));
    }

    public function testHashUsesModernAlgorithm(): void
    {
        $hash = (new PasswordHasher())->hash('correct horse battery');

        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $hash);
    }

    public function testBurnDoesNotThrow(): void
    {
        (new PasswordHasher())->burn('anything');
        $this->addToAssertionCount(1);
    }

    public function testLegacyBcryptWithLowCostNeedsRehash(): void
    {
        $legacy = password_hash('x', PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertTrue((new PasswordHasher())->needsRehash($legacy));
    }
}
