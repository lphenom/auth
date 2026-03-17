<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\Contracts\AuditListenerInterface;
use LPhenom\Log\Contract\LoggerInterface;

/**
 * Audit listener that logs auth events via lphenom/log.
 *
 * Never logs plaintext passwords or tokens.
 *
 * KPHP-compatible: no reflection, no callable.
  * @lphenom-build shared,kphp
 */
final class LogAuditListener implements AuditListenerInterface
{
    /** @var LoggerInterface */
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onLoginSuccess(string $login, string $userId): void
    {
        $this->logger->info('auth.login.success', ['login' => $login, 'user_id' => $userId]);
    }

    public function onLoginFailed(string $login): void
    {
        $this->logger->warning('auth.login.failed', ['login' => $login]);
    }

    public function onTokenIssued(string $userId, string $tokenId): void
    {
        $this->logger->info('auth.token.issued', ['user_id' => $userId, 'token_id' => $tokenId]);
    }

    public function onTokenRevoked(string $tokenId): void
    {
        $this->logger->info('auth.token.revoked', ['token_id' => $tokenId]);
    }

    public function onInvalidBearerAttempt(string $reason): void
    {
        $this->logger->warning('auth.bearer.invalid', ['reason' => $reason]);
    }
}
