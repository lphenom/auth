<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

use LPhenom\Auth\DTO\IssuedToken;
use LPhenom\Auth\DTO\ParsedToken;

/**
 * Token encoder/decoder contract.
 *
 * Responsible for:
 * - Issuing new opaque bearer tokens
 * - Parsing bearer token strings
 * - Hashing token secrets for storage
 *
 * KPHP-compatible: no reflection, no JWT.
 */
interface TokenEncoderInterface
{
    /**
     * Issue a new bearer token for the given user.
     *
     * @param string $userId
     * @param int    $ttlSeconds Token TTL in seconds
     *
     * @return IssuedToken
     */
    public function issue(string $userId, int $ttlSeconds): IssuedToken;

    /**
     * Parse a "tokenId.secret" bearer token string.
     */
    public function parseBearer(string $bearerToken): ?ParsedToken;

    /**
     * Hash a plain token secret for storage comparison.
     */
    public function hashToken(string $plainSecret): string;
}

