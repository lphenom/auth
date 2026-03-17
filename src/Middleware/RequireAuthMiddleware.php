<?php

declare(strict_types=1);

namespace LPhenom\Auth\Middleware;

use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

/**
 * Middleware that requires a valid bearer token.
 *
 * Returns 401 if no valid token is provided.
 *
 * KPHP-compatible: no reflection, no callable.
  * @lphenom-build shared,kphp
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
    /** @var BearerTokenGuard */
    private BearerTokenGuard $guard;

    public function __construct(BearerTokenGuard $guard)
    {
        $this->guard = $guard;
    }

    public function process(Request $request, Next $next): Response
    {
        $user = $this->guard->authenticate($request);

        if ($user === null) {
            return Response::json(
                ['error' => 'Unauthorized'],
                401
            );
        }

        return $next->handle($request);
    }
}
