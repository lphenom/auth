<?php

declare(strict_types=1);

namespace LPhenom\Auth\Middleware;

use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

/**
 * Middleware that requires the authenticated user to have specific roles.
 *
 * Returns 403 if the user does not have a required role.
 * Must be placed AFTER RequireAuthMiddleware in the pipeline.
 *
 * KPHP-compatible: no reflection, no callable.
 */
final class RequireRoleMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $requiredRoles;

    /**
     * @param string[] $requiredRoles
     */
    public function __construct(array $requiredRoles)
    {
        $this->requiredRoles = $requiredRoles;
    }

    public function process(Request $request, Next $next): Response
    {
        $ctx = AuthContextStorage::get();
        if ($ctx === null) {
            return Response::json(
                ['error' => 'Unauthorized'],
                401
            );
        }

        $userRoles = $ctx->user->getAuthRoles();

        foreach ($this->requiredRoles as $required) {
            if (!in_array($required, $userRoles, true)) {
                return Response::json(
                    ['error' => 'Forbidden'],
                    403
                );
            }
        }

        return $next->handle($request);
    }
}

