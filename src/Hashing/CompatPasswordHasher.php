<?php

declare(strict_types=1);

namespace LPhenom\Auth\Hashing;

use LPhenom\Auth\Contracts\PasswordHasherInterface;

/**
 * Compatibility password hasher for bidirectional migration between shared and kphp builds.
 *
 * Supports two hash formats simultaneously:
 *   - bcrypt    : "$2y$...", "$2b$...", "$2a$..." — produced by BcryptPasswordHasher (shared)
 *   - lphenom   : "$lphenom$sha256$..." — produced by CryptPasswordHasher (kphp)
 *
 * Migration paths:
 *
 *   shared → kphp:
 *     1. Replace BcryptPasswordHasher with CompatPasswordHasher (still running shared PHP).
 *     2. On each user login: verify() succeeds with bcrypt hash,
 *        needsRehash() returns true → DefaultAuthManager rehashes to lphenom format via
 *        PasswordHashUpdaterInterface::updateAuthPasswordHash().
 *     3. After all users have logged in once, all hashes are in lphenom format.
 *     4. Switch to KPHP build. CryptPasswordHasher takes over — zero data loss.
 *
 *   kphp → shared:
 *     1. Switch to shared build using CompatPasswordHasher.
 *     2. All existing lphenom hashes are verified directly — users log in immediately.
 *     3. needsRehash() returns false for lphenom hashes — no forced migration needed.
 *     4. New hashes are written in lphenom format (KPHP-compatible if you switch back).
 *
 * NEVER use in KPHP build: uses password_verify() which is not available in KPHP.
 *
 * @lphenom-build shared
 */
final class CompatPasswordHasher implements PasswordHasherInterface
{
    /** @var CryptPasswordHasher */
    private CryptPasswordHasher $inner;

    public function __construct(int $iterations = 10000)
    {
        $this->inner = new CryptPasswordHasher($iterations);
    }

    /**
     * Always produces a lphenom hash (kphp-compatible format).
     * New passwords written during migration are immediately usable by CryptPasswordHasher.
     */
    public function hash(string $plain): string
    {
        return $this->inner->hash($plain);
    }

    /**
     * Verifies against both bcrypt and lphenom hash formats.
     *
     * bcrypt ("$2y$", "$2b$", "$2a$")  → password_verify()
     * lphenom ("$lphenom$sha256$...")  → CryptPasswordHasher::verify()
     */
    public function verify(string $plain, string $stored): bool
    {
        if ($this->isBcrypt($stored)) {
            return password_verify($plain, $stored);
        }

        return $this->inner->verify($plain, $stored);
    }

    /**
     * Returns true for bcrypt hashes (migration needed) and for lphenom hashes
     * whose iteration count differs from the configured value.
     *
     * DefaultAuthManager calls hash() + PasswordHashUpdaterInterface::updateAuthPasswordHash()
     * when this returns true.
     */
    public function needsRehash(string $hash): bool
    {
        // Bcrypt always needs migration to lphenom format
        if ($this->isBcrypt($hash)) {
            return true;
        }

        // Lphenom: check if iteration count changed
        return $this->inner->needsRehash($hash);
    }

    /**
     * Detects bcrypt hash format by prefix.
     */
    private function isBcrypt(string $hash): bool
    {
        $prefix = substr($hash, 0, 4);
        return $prefix === '$2y$' || $prefix === '$2b$' || $prefix === '$2a$';
    }
}

