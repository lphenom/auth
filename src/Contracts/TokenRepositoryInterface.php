<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

use LPhenom\Auth\DTO\TokenRecord;

/**
 * Token persistence contract.
 *
 * Stores token hashes — never plaintext tokens.
 *
 * KPHP-compatible: no reflection, no ORM.
 */
interface TokenRepositoryInterface
{
    /**
     * Persist a new token record.
     */
    public function create(TokenRecord $token): void;

    /**
     * Find a token record by its token ID (not the hash).
     */
    public function findByTokenId(string $tokenId): ?TokenRecord;

    /**
     * Revoke a single token by its token ID.
     */
    public function revoke(string $tokenId): void;

    /**
     * Revoke all tokens for a given user.
     */
    public function revokeAllForUser(string $userId): void;
}

