<?php

declare(strict_types=1);

namespace LPhenom\Auth\Exceptions;

/**
 * Thrown when authentication is required but not provided (HTTP 401).
  * @lphenom-build shared,kphp
 */
class UnauthorizedException extends AuthException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}
