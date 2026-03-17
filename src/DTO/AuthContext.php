<?php

declare(strict_types=1);

namespace LPhenom\Auth\DTO;

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;

/**
 * Auth context — holds the current authenticated user and token info for a request.
 *
 * KPHP-compatible: no constructor property promotion, no readonly.
  * @lphenom-build shared,kphp
 */
final class AuthContext
{
    /** @var AuthenticatedUserInterface */
    public AuthenticatedUserInterface $user;

    /** @var string */
    public string $tokenId;

    /** @var string[] */
    public array $scopes;

    /**
     * @param string[] $scopes
     */
    public function __construct(
        AuthenticatedUserInterface $user,
        string $tokenId,
        array $scopes
    ) {
        $this->user    = $user;
        $this->tokenId = $tokenId;
        $this->scopes  = $scopes;
    }
}
