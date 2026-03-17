<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Login throttle (rate limiting) contract.
 *
 * KPHP-compatible: no callable, no reflection.
  * @lphenom-build shared,kphp
 */
interface LoginThrottleInterface
{
    /**
     * Record a failed login attempt for the given key (e.g. "ip:login").
     */
    public function hit(string $key, int $decaySeconds): void;

    /**
     * Check if too many attempts have been made.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Reset the attempt counter for the given key.
     */
    public function reset(string $key): void;
}
