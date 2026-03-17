<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Optional interface for user providers that support automatic password rehashing.
 *
 * When implemented by UserProviderInterface, DefaultAuthManager will call
 * updateAuthPasswordHash() after a successful login whenever
 * PasswordHasherInterface::needsRehash() returns true.
 *
 * This enables seamless migration between hash formats, e.g.:
 *   - shared  → kphp : bcrypt hashes are rehashed to lphenom format on login
 *   - kphp    → shared: lphenom hashes remain valid (CompatPasswordHasher verifies both)
 *
 * KPHP-compatible: no reflection, no callable.
 * @lphenom-build shared,kphp
 */
interface PasswordHashUpdaterInterface
{
    /**
     * Persist a new password hash for the given user.
     *
     * Called automatically during attempt() when the existing hash format
     * is outdated (needsRehash() === true).
     *
     * @param string $userId  The auth identifier (AuthenticatedUserInterface::getAuthIdentifier())
     * @param string $newHash The new hash produced by PasswordHasherInterface::hash()
     */
    public function updateAuthPasswordHash(string $userId, string $newHash): void;
}

