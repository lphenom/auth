<?php

declare(strict_types=1);

namespace LPhenom\Auth\Exceptions;

/**
 * Thrown when bearer token has been revoked.
 */
class RevokedTokenException extends AuthException
{
    public function __construct(string $message = 'Token has been revoked')
    {
        parent::__construct($message, 401);
    }
}

