<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

use LPhenom\Auth\DTO\IssuedToken;

/**
 * Auth manager contract — main entry point for authentication operations.
 *
 * KPHP-compatible: no reflection, no callable.
  * @lphenom-build shared,kphp
 */
interface AuthManagerInterface
{
    /**
     * Attempt to authenticate a user by login and password.
     * Returns the user on success, null on failure.
     */
    public function attempt(string $login, string $password): ?AuthenticatedUserInterface;

    /**
     * Issue a new bearer token for the given user.
     *
     * @param string $metaJson Optional JSON metadata to store with the token
     */
    public function issueToken(AuthenticatedUserInterface $user, string $metaJson = ''): IssuedToken;

    /**
     * Authenticate by parsing the Authorization header value.
     * Expects "Bearer <token>" format.
     * Returns the authenticated user or null.
     */
    public function authenticateBearer(?string $authorizationHeader): ?AuthenticatedUserInterface;

    /**
     * Revoke a bearer token (logout).
     */
    public function logoutToken(string $plainBearerToken): void;
}
