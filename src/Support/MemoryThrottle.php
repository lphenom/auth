<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\Contracts\LoginThrottleInterface;

/**
 * In-memory login throttle — for testing and single-request lifetime.
 *
 * KPHP-compatible: no reflection, no callable.
 */
final class MemoryThrottle implements LoginThrottleInterface
{
    /** @var array<string, int> */
    private array $attempts = [];

    public function hit(string $key, int $decaySeconds): void
    {
        $existing = $this->attempts[$key] ?? null;
        if ($existing === null) {
            $this->attempts[$key] = 1;
        } else {
            $this->attempts[$key] = $existing + 1;
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $existing = $this->attempts[$key] ?? null;
        if ($existing === null) {
            return false;
        }
        return $existing >= $maxAttempts;
    }

    public function reset(string $key): void
    {
        unset($this->attempts[$key]);
    }
}
