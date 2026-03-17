<?php

declare(strict_types=1);

namespace LPhenom\Auth\DTO;

/**
 * Parsed bearer token — result of parsing "tokenId.secret".
 *
 * KPHP-compatible: no constructor property promotion, no readonly.
 */
final class ParsedToken
{
    /** @var string */
    public string $tokenId;

    /** @var string */
    public string $secret;

    public function __construct(string $tokenId, string $secret)
    {
        $this->tokenId = $tokenId;
        $this->secret  = $secret;
    }
}
