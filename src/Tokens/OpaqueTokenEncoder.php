<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tokens;

use LPhenom\Auth\Contracts\TokenEncoderInterface;
use LPhenom\Auth\DTO\IssuedToken;
use LPhenom\Auth\DTO\ParsedToken;

/**
 * Opaque bearer token encoder.
 *
 * Token format: "<tokenId>.<secret>"
 * - tokenId: 32 hex chars (16 random bytes)
 * - secret: 64 hex chars (32 random bytes)
 *
 * Only the SHA-256 hash of the secret is stored — never the plaintext.
 *
 * KPHP-compatible: no reflection, no JWT, uses random_bytes + hash.
  * @lphenom-build shared,kphp
 */
final class OpaqueTokenEncoder implements TokenEncoderInterface
{
    public function issue(string $userId, int $ttlSeconds): IssuedToken
    {
        $tokenId = bin2hex(random_bytes(16));
        $secret  = bin2hex(random_bytes(32));

        $plainTextToken = $tokenId . '.' . $secret;

        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+' . $ttlSeconds . ' seconds');
        $expiresAtStr = $expiresAt->format('Y-m-d H:i:s');

        return new IssuedToken($plainTextToken, $tokenId, $expiresAtStr);
    }

    public function parseBearer(string $bearerToken): ?ParsedToken
    {
        $dotPos = strpos($bearerToken, '.');
        if ($dotPos === false) {
            return null;
        }

        $tokenId = (string) substr($bearerToken, 0, $dotPos);
        $secret  = (string) substr($bearerToken, $dotPos + 1);

        if ($tokenId === '' || $secret === '') {
            return null;
        }

        return new ParsedToken($tokenId, $secret);
    }

    public function hashToken(string $plainSecret): string
    {
        return hash('sha256', $plainSecret);
    }
}
