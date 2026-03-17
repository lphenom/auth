<?php

declare(strict_types=1);

namespace LPhenom\Auth\DTO;

/**
 * Token record stored in the database.
 *
 * KPHP-compatible: no constructor property promotion, no readonly.
 */
final class TokenRecord
{
    /** @var string */
    public string $tokenId;

    /** @var string */
    public string $userId;

    /** @var string */
    public string $tokenHash;

    /** @var string ISO 8601 datetime */
    public string $createdAt;

    /** @var string ISO 8601 datetime */
    public string $expiresAt;

    /** @var ?string ISO 8601 datetime or null */
    public ?string $revokedAt;

    /** @var string JSON string or empty */
    public string $metaJson;

    public function __construct(
        string $tokenId,
        string $userId,
        string $tokenHash,
        string $createdAt,
        string $expiresAt,
        ?string $revokedAt,
        string $metaJson
    ) {
        $this->tokenId   = $tokenId;
        $this->userId    = $userId;
        $this->tokenHash = $tokenHash;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->revokedAt = $revokedAt;
        $this->metaJson  = $metaJson;
    }

    /**
     * Check if the token has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool
    {
        $now = new \DateTimeImmutable();
        $exp = new \DateTimeImmutable($this->expiresAt);
        return $now > $exp;
    }
}
