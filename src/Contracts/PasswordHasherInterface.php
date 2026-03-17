<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Password hashing contract.
 *
 * KPHP-compatible: uses native password_hash/password_verify.
 */
interface PasswordHasherInterface
{
    /**
     * Hash a plaintext password.
     */
    public function hash(string $plain): string;

    /**
     * Verify a plaintext password against a hash.
     */
    public function verify(string $plain, string $hash): bool;

    /**
     * Check if the hash needs to be rehashed (e.g. cost changed).
     */
    public function needsRehash(string $hash): bool;
}

