<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support;

use LPhenom\Auth\Contracts\TokenRepositoryInterface;
use LPhenom\Auth\DTO\TokenRecord;

/**
 * In-memory token repository — for testing.
 *
 * KPHP-compatible: no reflection, no callable.
  * @lphenom-build shared,kphp
 */
final class InMemoryTokenRepository implements TokenRepositoryInterface
{
    /** @var array<string, TokenRecord> keyed by tokenId */
    private array $tokens = [];

    public function create(TokenRecord $token): void
    {
        $this->tokens[$token->tokenId] = $token;
    }

    public function findByTokenId(string $tokenId): ?TokenRecord
    {
        $record = $this->tokens[$tokenId] ?? null;
        return $record;
    }

    public function revoke(string $tokenId): void
    {
        $record = $this->tokens[$tokenId] ?? null;
        if ($record === null) {
            return;
        }
        $now = new \DateTimeImmutable();
        $this->tokens[$tokenId] = new TokenRecord(
            $record->tokenId,
            $record->userId,
            $record->tokenHash,
            $record->createdAt,
            $record->expiresAt,
            $now->format('Y-m-d H:i:s'),
            $record->metaJson
        );
    }

    public function revokeAllForUser(string $userId): void
    {
        $now = new \DateTimeImmutable();
        $nowStr = $now->format('Y-m-d H:i:s');

        /** @var array<string, TokenRecord> $updated */
        $updated = [];
        foreach ($this->tokens as $id => $record) {
            if ($record->userId === $userId && $record->revokedAt === null) {
                $updated[$id] = new TokenRecord(
                    $record->tokenId,
                    $record->userId,
                    $record->tokenHash,
                    $record->createdAt,
                    $record->expiresAt,
                    $nowStr,
                    $record->metaJson
                );
            } else {
                $updated[$id] = $record;
            }
        }
        $this->tokens = $updated;
    }
}
