<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\Contracts\AuditListenerInterface;
use LPhenom\Auth\Contracts\AuthenticatedUserInterface;
use LPhenom\Auth\Contracts\AuthManagerInterface;
use LPhenom\Auth\Contracts\LoginThrottleInterface;
use LPhenom\Auth\Contracts\PasswordHasherInterface;
use LPhenom\Auth\Contracts\TokenEncoderInterface;
use LPhenom\Auth\Contracts\TokenRepositoryInterface;
use LPhenom\Auth\Contracts\UserProviderInterface;
use LPhenom\Auth\DTO\AuthContext;
use LPhenom\Auth\DTO\IssuedToken;
use LPhenom\Auth\DTO\TokenRecord;

/**
 * Default auth manager implementation.
 *
 * Orchestrates login, token issuing, bearer authentication, and logout.
 *
 * KPHP-compatible: no reflection, no callable, no match, no union types.
  * @lphenom-build shared,kphp
 */
final class DefaultAuthManager implements AuthManagerInterface
{
    /** @var UserProviderInterface */
    private UserProviderInterface $userProvider;

    /** @var PasswordHasherInterface */
    private PasswordHasherInterface $hasher;

    /** @var TokenEncoderInterface */
    private TokenEncoderInterface $tokenEncoder;

    /** @var TokenRepositoryInterface */
    private TokenRepositoryInterface $tokenRepo;

    /** @var ?LoginThrottleInterface */
    private ?LoginThrottleInterface $throttle;

    /** @var ?AuditListenerInterface */
    private ?AuditListenerInterface $audit;

    /** @var int Token TTL in seconds (default 24 hours) */
    private int $tokenTtl;

    /** @var int Max login attempts before throttle (default 5) */
    private int $maxAttempts;

    /** @var int Throttle decay time in seconds (default 60) */
    private int $throttleDecay;

    public function __construct(
        UserProviderInterface $userProvider,
        PasswordHasherInterface $hasher,
        TokenEncoderInterface $tokenEncoder,
        TokenRepositoryInterface $tokenRepo,
        ?LoginThrottleInterface $throttle,
        ?AuditListenerInterface $audit,
        int $tokenTtl,
        int $maxAttempts,
        int $throttleDecay
    ) {
        $this->userProvider  = $userProvider;
        $this->hasher        = $hasher;
        $this->tokenEncoder  = $tokenEncoder;
        $this->tokenRepo     = $tokenRepo;
        $this->throttle      = $throttle;
        $this->audit         = $audit;
        $this->tokenTtl      = $tokenTtl;
        $this->maxAttempts   = $maxAttempts;
        $this->throttleDecay = $throttleDecay;
    }

    public function attempt(string $login, string $password): ?AuthenticatedUserInterface
    {
        $throttleKey = 'auth:login:' . $login;

        // Check throttle
        if ($this->throttle !== null) {
            if ($this->throttle->tooManyAttempts($throttleKey, $this->maxAttempts)) {
                if ($this->audit !== null) {
                    $this->audit->onLoginFailed($login);
                }
                return null;
            }
        }

        $user = $this->userProvider->findByLogin($login);
        if ($user === null) {
            $this->onLoginFail($login, $throttleKey);
            return null;
        }

        if (!$user->isActive()) {
            $this->onLoginFail($login, $throttleKey);
            return null;
        }

        $passwordHash = $user->getAuthPasswordHash();
        if ($passwordHash === null) {
            $this->onLoginFail($login, $throttleKey);
            return null;
        }

        if (!$this->hasher->verify($password, $passwordHash)) {
            $this->onLoginFail($login, $throttleKey);
            return null;
        }

        // Success — reset throttle
        if ($this->throttle !== null) {
            $this->throttle->reset($throttleKey);
        }

        if ($this->audit !== null) {
            $this->audit->onLoginSuccess($login, $user->getAuthIdentifier());
        }

        return $user;
    }

    public function issueToken(AuthenticatedUserInterface $user, string $metaJson = ''): IssuedToken
    {
        $userId = $user->getAuthIdentifier();
        $issued = $this->tokenEncoder->issue($userId, $this->tokenTtl);

        // Parse to get the secret for hashing
        $parsed = $this->tokenEncoder->parseBearer($issued->plainTextToken);
        $tokenHash = '';
        if ($parsed !== null) {
            $tokenHash = $this->tokenEncoder->hashToken($parsed->secret);
        }

        $now = new \DateTimeImmutable();
        $record = new TokenRecord(
            $issued->tokenId,
            $userId,
            $tokenHash,
            $now->format('Y-m-d H:i:s'),
            $issued->expiresAt,
            null,
            $metaJson
        );

        $this->tokenRepo->create($record);

        if ($this->audit !== null) {
            $this->audit->onTokenIssued($userId, $issued->tokenId);
        }

        return $issued;
    }

    public function authenticateBearer(?string $authorizationHeader): ?AuthenticatedUserInterface
    {
        if ($authorizationHeader === null) {
            return null;
        }

        // Extract "Bearer <token>"
        $prefix = 'Bearer ';
        if ((string) substr($authorizationHeader, 0, strlen($prefix)) !== $prefix) {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('missing Bearer prefix');
            }
            return null;
        }

        $plainToken = (string) substr($authorizationHeader, strlen($prefix));
        if ($plainToken === '') {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('empty token');
            }
            return null;
        }

        $parsed = $this->tokenEncoder->parseBearer($plainToken);
        if ($parsed === null) {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('malformed token');
            }
            return null;
        }

        $record = $this->tokenRepo->findByTokenId($parsed->tokenId);
        if ($record === null) {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('token not found');
            }
            return null;
        }

        // Verify hash
        $expectedHash = $this->tokenEncoder->hashToken($parsed->secret);
        if (!hash_equals($record->tokenHash, $expectedHash)) {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('hash mismatch');
            }
            return null;
        }

        // Check revoked
        if ($record->isRevoked()) {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('token revoked');
            }
            return null;
        }

        // Check expired
        if ($record->isExpired()) {
            if ($this->audit !== null) {
                $this->audit->onInvalidBearerAttempt('token expired');
            }
            return null;
        }

        $user = $this->userProvider->findById($record->userId);
        if ($user === null) {
            return null;
        }

        if (!$user->isActive()) {
            return null;
        }

        // Store auth context
        /** @var string[] $scopes */
        $scopes = [];
        $ctx = new AuthContext($user, $record->tokenId, $scopes);
        AuthContextStorage::set($ctx);

        return $user;
    }

    public function logoutToken(string $plainBearerToken): void
    {
        $parsed = $this->tokenEncoder->parseBearer($plainBearerToken);
        if ($parsed === null) {
            return;
        }

        $this->tokenRepo->revoke($parsed->tokenId);

        if ($this->audit !== null) {
            $this->audit->onTokenRevoked($parsed->tokenId);
        }
    }

    private function onLoginFail(string $login, string $throttleKey): void
    {
        if ($this->throttle !== null) {
            $this->throttle->hit($throttleKey, $this->throttleDecay);
        }
        if ($this->audit !== null) {
            $this->audit->onLoginFailed($login);
        }
    }
}
