<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Support;

use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Auth\DTO\AuthContext;
use PHPUnit\Framework\TestCase;

final class AuthContextStorageTest extends TestCase
{
    protected function setUp(): void
    {
        AuthContextStorage::reset();
    }

    public function testInitiallyNull(): void
    {
        self::assertNull(AuthContextStorage::get());
    }

    public function testSetAndGet(): void
    {
        $user = new StubUser('1', 'test', null, [], true);
        $ctx = new AuthContext($user, 'token-1', []);

        AuthContextStorage::set($ctx);
        $result = AuthContextStorage::get();

        self::assertNotNull($result);
        self::assertSame('1', $result->user->getAuthIdentifier());
        self::assertSame('token-1', $result->tokenId);
    }

    public function testResetClearsContext(): void
    {
        $user = new StubUser('1', 'test', null, [], true);
        $ctx = new AuthContext($user, 'token-1', []);

        AuthContextStorage::set($ctx);
        self::assertNotNull(AuthContextStorage::get());

        AuthContextStorage::reset();
        self::assertNull(AuthContextStorage::get());
    }
}

