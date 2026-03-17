<?php

declare(strict_types=1);

namespace LPhenom\Auth\Exceptions;

/**
 * Thrown when login credentials are invalid.
  * @lphenom-build shared,kphp
 */
class InvalidCredentialsException extends AuthException
{
    public function __construct(string $message = 'Invalid credentials')
    {
        parent::__construct($message, 401);
    }
}
