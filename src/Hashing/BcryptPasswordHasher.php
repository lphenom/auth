<?php

declare(strict_types=1);

namespace LPhenom\Auth\Hashing;

use LPhenom\Auth\Contracts\PasswordHasherInterface;

/**
 * Bcrypt password hasher using native password_hash/password_verify.
 *
 * KPHP-compatible: no reflection, uses only native functions.
  * @lphenom-build shared
 */
final class BcryptPasswordHasher implements PasswordHasherInterface
{
    /** @var int */
    private int $cost;

    public function __construct(int $cost = 10)
    {
        $this->cost = $cost;
    }

    public function hash(string $plain): string
    {
        /** @var array<string, int> $options */
        $options = ['cost' => $this->cost];
        $result = password_hash($plain, PASSWORD_BCRYPT, $options);

        return $result;
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        /** @var array<string, int> $options */
        $options = ['cost' => $this->cost];
        return password_needs_rehash($hash, PASSWORD_BCRYPT, $options);
    }
}
