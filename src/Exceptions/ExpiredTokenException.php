<?php

declare(strict_types=1);

namespace LPhenom\Auth\Exceptions;

/**
 * Thrown when bearer token has expired.
  * @lphenom-build shared,kphp
 */
class ExpiredTokenException extends AuthException
{
    public function __construct(string $message = 'Token has expired')
    {
        parent::__construct($message, 401);
    }
}
