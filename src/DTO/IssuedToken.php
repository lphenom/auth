<?php

declare(strict_types=1);

namespace LPhenom\Auth\DTO;

/**
 * Issued token — returned to the client after successful authentication.
 *
 * Contains the plaintext token (shown once) and metadata.
 *
 * KPHP-compatible: no constructor property promotion, no readonly.
 */
final class IssuedToken
{
    /** @var string The full "tokenId.secret" plaintext token */
    public string $plainTextToken;

    /** @var string The token ID portion */
    public string $tokenId;

    /** @var string ISO 8601 expiration datetime */
    public string $expiresAt;

    public function __construct(
        string $plainTextToken,
        string $tokenId,
        string $expiresAt
    ) {
        $this->plainTextToken = $plainTextToken;
        $this->tokenId        = $tokenId;
        $this->expiresAt      = $expiresAt;
    }
}
