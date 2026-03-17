<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Audit listener interface for auth events.
 *
 * KPHP-compatible: explicit interface instead of callable hooks.
 */
interface AuditListenerInterface
{
    /**
     * Called on successful login.
     */
    public function onLoginSuccess(string $login, string $userId): void;

    /**
     * Called on failed login attempt.
     */
    public function onLoginFailed(string $login): void;

    /**
     * Called when a new token is issued.
     */
    public function onTokenIssued(string $userId, string $tokenId): void;

    /**
     * Called when a token is revoked.
     */
    public function onTokenRevoked(string $tokenId): void;

    /**
     * Called when an invalid bearer token is presented.
     */
    public function onInvalidBearerAttempt(string $reason): void;
}
