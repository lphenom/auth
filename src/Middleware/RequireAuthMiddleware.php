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
 * Public paths (no auth required) can be passed as the second constructor
 * argument. Two matching modes are supported:
 *   - Exact match:           '/api/v1/auth/login'
 *   - Prefix wildcard ('*'): '/api/v1/auth/*'  — matches any path under /api/v1/auth/
 *
 * Example:
 *   new RequireAuthMiddleware($guard, ['/api/v1/auth/login', '/api/v1/auth/register'])
 *
 * KPHP-compatible: no reflection, no callable.
 * @lphenom-build shared,kphp
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
    /** @var BearerTokenGuard */
    private BearerTokenGuard $guard;

    /** @var string[] */
    private array $publicPaths;

    /**
     * @param string[] $publicPaths Paths that bypass authentication.
     */
    public function __construct(BearerTokenGuard $guard, array $publicPaths = [])
    {
        $this->guard       = $guard;
        $this->publicPaths = $publicPaths;
    }

    public function process(Request $request, Next $next): Response
    {
        if ($this->isPublicPath($request->getPath())) {
            return $next->handle($request);
        }

        $user = $this->guard->authenticate($request);

        if ($user === null) {
            return Response::json(
                ['error' => 'Unauthorized'],
                401
            );
        }

        return $next->handle($request);
    }

    private function isPublicPath(string $path): bool
    {
        foreach ($this->publicPaths as $publicPath) {
            if (str_ends_with($publicPath, '*')) {
                // Prefix wildcard: '/api/v1/auth/*' → prefix '/api/v1/auth/'
                $prefix = substr($publicPath, 0, -1);
                if ($prefix !== '' && str_starts_with($path, $prefix)) {
                    return true;
                }
            } elseif ($path === $publicPath) {
                return true;
            }
        }
        return false;
    }
}
