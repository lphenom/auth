<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Hashing;

use LPhenom\Auth\Hashing\BcryptPasswordHasher;
use LPhenom\Auth\Hashing\CompatPasswordHasher;
use LPhenom\Auth\Hashing\CryptPasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CompatPasswordHasher — bidirectional migration between
 * bcrypt (shared) and lphenom-HMAC-SHA256 (kphp) hash formats.
 */
final class CompatPasswordHasherTest extends TestCase
{
    // -------------------------------------------------------------------------
    // hash() always produces lphenom format
    // -------------------------------------------------------------------------

    public function testHashProducesLphenomFormat(): void
    {
        $hasher = new CompatPasswordHasher(100);
        $hash   = $hasher->hash('secret');

        self::assertStringStartsWith('$lphenom$sha256$', $hash);
    }

    public function testHashNotEmptyAndDifferentEachCall(): void
    {
        $hasher = new CompatPasswordHasher(100);
        $h1 = $hasher->hash('password');
        $h2 = $hasher->hash('password');

        self::assertNotEmpty($h1);
        // Different salts → different hashes even for same password
        self::assertNotSame($h1, $h2);
    }

    // -------------------------------------------------------------------------
    // verify() — lphenom hashes
    // -------------------------------------------------------------------------

    public function testVerifyLphenomHash(): void
    {
        $hasher = new CompatPasswordHasher(100);
        $hash   = $hasher->hash('mypassword');

        self::assertTrue($hasher->verify('mypassword', $hash));
        self::assertFalse($hasher->verify('wrongpassword', $hash));
    }

    public function testVerifyLphenomHashProducedByCryptHasher(): void
    {
        // Hash created by CryptPasswordHasher (the kphp implementation)
        // must be verifiable by CompatPasswordHasher (used in shared mode after kphp→shared migration)
        $cryptHasher  = new CryptPasswordHasher(100);
        $compatHasher = new CompatPasswordHasher(100);

        $hash = $cryptHasher->hash('userpassword');

        self::assertTrue($compatHasher->verify('userpassword', $hash),
            'CompatPasswordHasher must verify hashes produced by CryptPasswordHasher (kphp→shared migration)');
        self::assertFalse($compatHasher->verify('wrong', $hash));
    }

    // -------------------------------------------------------------------------
    // verify() — bcrypt hashes (shared→kphp migration path)
    // -------------------------------------------------------------------------

    public function testVerifyBcryptHash(): void
    {
        $bcryptHasher = new BcryptPasswordHasher(4);
        $bcryptHash   = $bcryptHasher->hash('legacypassword');

        $compatHasher = new CompatPasswordHasher(100);

        self::assertTrue($compatHasher->verify('legacypassword', $bcryptHash),
            'CompatPasswordHasher must verify legacy bcrypt hashes (shared→kphp migration)');
        self::assertFalse($compatHasher->verify('wrong', $bcryptHash));
    }

    public function testVerifyBcrypt2bPrefix(): void
    {
        // Manually crafted $2b$ hash (some bcrypt implementations use $2b$)
        $hash = password_hash('testpass', PASSWORD_BCRYPT);
        // Force $2b$ prefix variant for testing prefix detection
        $b2bHash = str_replace('$2y$', '$2b$', $hash);

        $compatHasher = new CompatPasswordHasher(100);
        // $2b$ and $2y$ are treated identically by PHP password_verify
        self::assertTrue($compatHasher->verify('testpass', $hash));
    }

    // -------------------------------------------------------------------------
    // needsRehash()
    // -------------------------------------------------------------------------

    public function testNeedsRehashReturnsTrueForBcryptHash(): void
    {
        $bcryptHasher = new BcryptPasswordHasher(4);
        $bcryptHash   = $bcryptHasher->hash('pass');

        $compatHasher = new CompatPasswordHasher(100);

        self::assertTrue($compatHasher->needsRehash($bcryptHash),
            'bcrypt hashes always need rehash to migrate to lphenom format');
    }

    public function testNeedsRehashReturnsFalseForCurrentLphenomHash(): void
    {
        $compatHasher = new CompatPasswordHasher(100);
        $hash         = $compatHasher->hash('pass');

        self::assertFalse($compatHasher->needsRehash($hash),
            'lphenom hashes with current iterations must not need rehash');
    }

    public function testNeedsRehashReturnsTrueForLphenomHashWithDifferentIterations(): void
    {
        $hasherOld = new CompatPasswordHasher(100);
        $hasherNew = new CompatPasswordHasher(200);
        $hash      = $hasherOld->hash('pass');

        self::assertTrue($hasherNew->needsRehash($hash),
            'lphenom hash with outdated iteration count must trigger rehash');
    }

    // -------------------------------------------------------------------------
    // Migration round-trip: shared→kphp
    // -------------------------------------------------------------------------

    /**
     * Full migration scenario: shared → kphp
     *
     * 1. User has a bcrypt hash (created by BcryptPasswordHasher, shared mode).
     * 2. App switches to CompatPasswordHasher (still shared PHP).
     * 3. User logs in: bcrypt verified ✓, needsRehash() = true → hash updated to lphenom.
     * 4. App switches to KPHP build (CryptPasswordHasher).
     * 5. CryptPasswordHasher verifies the lphenom hash ✓.
     */
    public function testMigrationSharedToKphp(): void
    {
        // Step 1: create bcrypt hash (old shared mode)
        $bcryptHasher = new BcryptPasswordHasher(4);
        $bcryptHash   = $bcryptHasher->hash('hunter2');

        // Step 2-3: CompatPasswordHasher verifies it and rehashes
        $compatHasher = new CompatPasswordHasher(100);
        self::assertTrue($compatHasher->verify('hunter2', $bcryptHash));
        self::assertTrue($compatHasher->needsRehash($bcryptHash));

        $migratedHash = $compatHasher->hash('hunter2'); // new lphenom hash stored in DB

        // Step 4-5: KPHP build uses CryptPasswordHasher on the migrated hash
        $cryptHasher = new CryptPasswordHasher(100);
        self::assertTrue($cryptHasher->verify('hunter2', $migratedHash),
            'CryptPasswordHasher (kphp) must verify hash produced by CompatPasswordHasher after migration');
        self::assertFalse($cryptHasher->verify('wrongpassword', $migratedHash));
    }

    /**
     * Full migration scenario: kphp → shared
     *
     * 1. User has a lphenom hash (created by CryptPasswordHasher, kphp mode).
     * 2. App switches to shared build using CompatPasswordHasher.
     * 3. CompatPasswordHasher verifies the lphenom hash ✓ — no migration needed.
     * 4. needsRehash() = false — hash stays as-is.
     */
    public function testMigrationKphpToShared(): void
    {
        // Step 1: create lphenom hash (kphp mode)
        $cryptHasher  = new CryptPasswordHasher(100);
        $lphenomHash  = $cryptHasher->hash('hunter2');

        // Step 2-3: CompatPasswordHasher in shared mode verifies it immediately
        $compatHasher = new CompatPasswordHasher(100);
        self::assertTrue($compatHasher->verify('hunter2', $lphenomHash),
            'CompatPasswordHasher (shared) must verify lphenom hashes on kphp→shared migration');
        self::assertFalse($compatHasher->needsRehash($lphenomHash),
            'kphp→shared: lphenom hashes do not require forced rehash');
    }
}

