<?php

declare(strict_types=1);

namespace LPhenom\Auth\Exceptions;

/**
 * Thrown when bearer token is malformed or not found in storage.
  * @lphenom-build shared,kphp
 */
class InvalidTokenException extends AuthException
{
    public function __construct(string $message = 'Invalid token')
    {
        parent::__construct($message, 401);
    }
}
