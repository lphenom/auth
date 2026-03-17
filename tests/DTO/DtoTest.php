<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\DTO;

use LPhenom\Auth\DTO\TokenRecord;
use LPhenom\Auth\DTO\IssuedToken;
use LPhenom\Auth\DTO\ParsedToken;
use LPhenom\Auth\DTO\AuthContext;
use PHPUnit\Framework\TestCase;

final class DtoTest extends TestCase
{
    public function testTokenRecordConstruction(): void
    {
        $record = new TokenRecord(
            'tid1',
            'uid1',
            'hash1',
            '2026-01-01 00:00:00',
            '2027-01-01 00:00:00',
            null,
            '{"scope":"read"}'
        );

        self::assertSame('tid1', $record->tokenId);
        self::assertSame('uid1', $record->userId);
        self::assertSame('hash1', $record->tokenHash);
        self::assertFalse($record->isRevoked());
        self::assertFalse($record->isExpired());
    }

    public function testTokenRecordExpired(): void
    {
        $record = new TokenRecord(
            'tid1',
            'uid1',
            'hash1',
            '2020-01-01 00:00:00',
            '2020-01-02 00:00:00',
            null,
            ''
        );

        self::assertTrue($record->isExpired());
    }

    public function testTokenRecordRevoked(): void
    {
        $record = new TokenRecord(
            'tid1',
            'uid1',
            'hash1',
            '2026-01-01 00:00:00',
            '2027-01-01 00:00:00',
            '2026-06-01 00:00:00',
            ''
        );

        self::assertTrue($record->isRevoked());
    }

    public function testIssuedToken(): void
    {
        $token = new IssuedToken('abc.def', 'abc', '2027-01-01 00:00:00');

        self::assertSame('abc.def', $token->plainTextToken);
        self::assertSame('abc', $token->tokenId);
        self::assertSame('2027-01-01 00:00:00', $token->expiresAt);
    }

    public function testParsedToken(): void
    {
        $parsed = new ParsedToken('tid', 'secret');

        self::assertSame('tid', $parsed->tokenId);
        self::assertSame('secret', $parsed->secret);
    }

    public function testAuthContext(): void
    {
        $user = new \LPhenom\Auth\Tests\Support\StubUser('1', 'test', null, ['admin'], true);
        $ctx = new AuthContext($user, 'tok-1', ['read', 'write']);

        self::assertSame('1', $ctx->user->getAuthIdentifier());
        self::assertSame('tok-1', $ctx->tokenId);
        self::assertSame(['read', 'write'], $ctx->scopes);
    }
}

