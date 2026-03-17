<?php

declare(strict_types=1);

namespace LPhenom\Auth\Guards;

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;
use LPhenom\Auth\Contracts\AuthManagerInterface;
use LPhenom\Http\Request;

/**
 * Bearer token guard — extracts and validates bearer token from HTTP request.
 *
 * KPHP-compatible: no reflection, no callable.
  * @lphenom-build shared,kphp
 */
final class BearerTokenGuard
{
    /** @var AuthManagerInterface */
    private AuthManagerInterface $authManager;

    public function __construct(AuthManagerInterface $authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Authenticate the request using the Authorization header.
     */
    public function authenticate(Request $request): ?AuthenticatedUserInterface
    {
        $header = $request->getHeader('Authorization');
        return $this->authManager->authenticateBearer($header);
    }
}
