<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Hashing;

use LPhenom\Auth\Hashing\BcryptPasswordHasher;
use PHPUnit\Framework\TestCase;

final class BcryptPasswordHasherTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $hasher = new BcryptPasswordHasher(4);

        $hash = $hasher->hash('secret123');
        self::assertNotEmpty($hash);
        self::assertTrue($hasher->verify('secret123', $hash));
        self::assertFalse($hasher->verify('wrong', $hash));
    }

    public function testDifferentPasswordsProduceDifferentHashes(): void
    {
        $hasher = new BcryptPasswordHasher(4);

        $hash1 = $hasher->hash('password1');
        $hash2 = $hasher->hash('password2');
        self::assertNotSame($hash1, $hash2);
    }

    public function testNeedsRehashWithDifferentCost(): void
    {
        $hasherLow  = new BcryptPasswordHasher(4);
        $hasherHigh = new BcryptPasswordHasher(10);

        $hash = $hasherLow->hash('test');
        self::assertTrue($hasherHigh->needsRehash($hash));
        self::assertFalse($hasherLow->needsRehash($hash));
    }
}
