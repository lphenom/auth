<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\DTO\AuthContext;
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Http\Request;

/**
 * HTTP Auth Bridge — connects auth system with the HTTP request pipeline.
 *
 * Convenience class to authenticate a request and store context in one call.
 *
 * KPHP-compatible: no reflection, no callable.
 */
final class HttpAuthBridge
{
    /** @var BearerTokenGuard */
    private BearerTokenGuard $guard;

    public function __construct(BearerTokenGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Authenticate the HTTP request and store auth context.
     * Returns true if authenticated, false otherwise.
     */
    public function authenticateRequest(Request $request): bool
    {
        $user = $this->guard->authenticate($request);
        return $user !== null;
    }

    /**
     * Get current auth context (after authenticateRequest).
     */
    public function getContext(): ?AuthContext
    {
        return AuthContextStorage::get();
    }
}
