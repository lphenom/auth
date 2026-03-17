<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\DTO\AuthContext;

/**
 * Request-scoped auth context storage.
 *
 * Uses a static variable to hold the current AuthContext for the lifetime
 * of a single request. Compatible with shared hosting and compiled (KPHP) runtime.
 *
 * Call reset() at the beginning of each request in compiled mode.
 *
 * KPHP-compatible: no reflection, no global magic.
  * @lphenom-build shared,kphp
 */
final class AuthContextStorage
{
    /** @var ?AuthContext */
    private static ?AuthContext $context = null;

    /**
     * Set the auth context for the current request.
     */
    public static function set(AuthContext $context): void
    {
        self::$context = $context;
    }

    /**
     * Get the current auth context, or null if not authenticated.
     */
    public static function get(): ?AuthContext
    {
        return self::$context;
    }

    /**
     * Clear the auth context (call at end of request or start of new request).
     */
    public static function reset(): void
    {
        self::$context = null;
    }
}
