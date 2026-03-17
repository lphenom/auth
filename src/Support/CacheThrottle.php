<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\Contracts\LoginThrottleInterface;
use LPhenom\Cache\CacheInterface;

/**
 * Cache-based login throttle.
 *
 * Uses lphenom/cache to track failed login attempts.
 *
 * KPHP-compatible: no reflection, no callable.
 */
final class CacheThrottle implements LoginThrottleInterface
{
    /** @var CacheInterface */
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function hit(string $key, int $decaySeconds): void
    {
        $cacheKey = 'auth_throttle:' . $key;
        $this->cache->increment($cacheKey, 1, $decaySeconds);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $cacheKey = 'auth_throttle:' . $key;
        $value = $this->cache->get($cacheKey);
        if ($value === null) {
            return false;
        }
        $attempts = (int) $value;
        return $attempts >= $maxAttempts;
    }

    public function reset(string $key): void
    {
        $cacheKey = 'auth_throttle:' . $key;
        $this->cache->delete($cacheKey);
    }
}

