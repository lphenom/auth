<?php

declare(strict_types=1);

namespace LPhenom\Auth\Exceptions;

/**
 * Thrown when user does not have required role/permissions (HTTP 403).
  * @lphenom-build shared,kphp
 */
class ForbiddenException extends AuthException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }
}
