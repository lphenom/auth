<?php

declare(strict_types=1);

namespace LPhenom\Auth\Hashing;

use LPhenom\Auth\Contracts\PasswordHasherInterface;

/**
 * KPHP-compatible password hasher using iterative HMAC-SHA256.
 *
 * Uses iterative hash_hmac('sha256') — a simplified PBKDF2 scheme — because
 * password_hash()/password_verify() are NOT available in KPHP.
 *
 * Hash format: $lphenom$sha256$<iterations>$<salt32hex>$<hash64hex>
 *
 * IMPORTANT — cross-mode compatibility:
 *   Hashes produced by CryptPasswordHasher and BcryptPasswordHasher are NOT
 *   interchangeable. If you run shared mode and KPHP mode against the same
 *   database, users must reset their passwords when switching modes.
 *   See docs/build-targets.md for migration guidance.
 *
 * KPHP-compatible: uses only hash_hmac(), hash_equals(), random_bytes(), bin2hex().
 *
 * @lphenom-build kphp
 */
final class CryptPasswordHasher implements PasswordHasherInterface
{
    /** @var int */
    private int $iterations;

    /** Prefix length for needsRehash check */
    private const PREFIX = '$lphenom$sha256$';

    public function __construct(int $iterations = 10000)
    {
        $this->iterations = $iterations;
    }

    public function hash(string $plain): string
    {
        $salt = bin2hex(random_bytes(16));
        $hash = $this->derive($plain, $salt, $this->iterations);

        return self::PREFIX . $this->iterations . '$' . $salt . '$' . $hash;
    }

    public function verify(string $plain, string $stored): bool
    {
        // Format: $lphenom$sha256$<iter>$<salt>$<hash>
        // Split: ['', 'lphenom', 'sha256', '<iter>', '<salt>', '<hash>']
        $parts = explode('$', $stored);
        if (count($parts) !== 6) {
            return false;
        }

        $iter = (int) $parts[3];
        $salt = $parts[4];
        $expected = $parts[5];

        if ($iter < 1 || $salt === '' || $expected === '') {
            return false;
        }

        $derived = $this->derive($plain, $salt, $iter);

        return hash_equals($expected, $derived);
    }

    public function needsRehash(string $hash): bool
    {
        $prefix = self::PREFIX . $this->iterations . '$';
        return substr($hash, 0, strlen($prefix)) !== $prefix;
    }

    private function derive(string $password, string $salt, int $iterations): string
    {
        // Iterative HMAC-SHA256 (PBKDF2-like, KPHP-compatible).
        // KPHP supports hash_hmac() — no str_starts_with, no Closure, no match.
        $h = hash_hmac('sha256', $salt, $password);
        $i = 1;
        while ($i < $iterations) {
            $h = hash_hmac('sha256', $h, $password);
            $i++;
        }
        return $h;
    }
}

