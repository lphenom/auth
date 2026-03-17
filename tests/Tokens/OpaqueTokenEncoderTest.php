<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Tokens;

use LPhenom\Auth\Tokens\OpaqueTokenEncoder;
use PHPUnit\Framework\TestCase;

final class OpaqueTokenEncoderTest extends TestCase
{
    public function testIssueReturnsValidToken(): void
    {
        $encoder = new OpaqueTokenEncoder();
        $issued = $encoder->issue('user-42', 3600);

        self::assertNotEmpty($issued->plainTextToken);
        self::assertNotEmpty($issued->tokenId);
        self::assertNotEmpty($issued->expiresAt);

        // Token format: tokenId.secret
        $parts = explode('.', $issued->plainTextToken);
        self::assertCount(2, $parts);
        self::assertSame($issued->tokenId, $parts[0]);
    }

    public function testParseBearerValid(): void
    {
        $encoder = new OpaqueTokenEncoder();
        $issued = $encoder->issue('user-1', 3600);

        $parsed = $encoder->parseBearer($issued->plainTextToken);
        self::assertNotNull($parsed);
        self::assertSame($issued->tokenId, $parsed->tokenId);
        self::assertNotEmpty($parsed->secret);
    }

    public function testParseBearerInvalid(): void
    {
        $encoder = new OpaqueTokenEncoder();

        self::assertNull($encoder->parseBearer(''));
        self::assertNull($encoder->parseBearer('no-dot-here'));
        self::assertNull($encoder->parseBearer('.empty-id'));
        self::assertNull($encoder->parseBearer('empty-secret.'));
    }

    public function testHashTokenIsDeterministic(): void
    {
        $encoder = new OpaqueTokenEncoder();

        $hash1 = $encoder->hashToken('my-secret');
        $hash2 = $encoder->hashToken('my-secret');
        self::assertSame($hash1, $hash2);
    }

    public function testHashTokenDiffersForDifferentSecrets(): void
    {
        $encoder = new OpaqueTokenEncoder();

        $hash1 = $encoder->hashToken('secret-a');
        $hash2 = $encoder->hashToken('secret-b');
        self::assertNotSame($hash1, $hash2);
    }

    public function testIssuedTokensAreUnique(): void
    {
        $encoder = new OpaqueTokenEncoder();

        $t1 = $encoder->issue('user-1', 3600);
        $t2 = $encoder->issue('user-1', 3600);
        self::assertNotSame($t1->plainTextToken, $t2->plainTextToken);
        self::assertNotSame($t1->tokenId, $t2->tokenId);
    }
}

