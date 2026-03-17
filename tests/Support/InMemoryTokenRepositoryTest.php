<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Support;

use LPhenom\Auth\Support\InMemoryTokenRepository;
use LPhenom\Auth\DTO\TokenRecord;
use PHPUnit\Framework\TestCase;

final class InMemoryTokenRepositoryTest extends TestCase
{
    public function testCreateAndFind(): void
    {
        $repo = new InMemoryTokenRepository();
        $record = new TokenRecord(
            'tid1',
            'user1',
            'hash1',
            '2026-01-01 00:00:00',
            '2027-01-01 00:00:00',
            null,
            ''
        );

        $repo->create($record);
        $found = $repo->findByTokenId('tid1');

        self::assertNotNull($found);
        self::assertSame('tid1', $found->tokenId);
        self::assertSame('user1', $found->userId);
        self::assertSame('hash1', $found->tokenHash);
    }

    public function testFindNonExistent(): void
    {
        $repo = new InMemoryTokenRepository();
        self::assertNull($repo->findByTokenId('nonexistent'));
    }

    public function testRevoke(): void
    {
        $repo = new InMemoryTokenRepository();
        $record = new TokenRecord(
            'tid1',
            'user1',
            'hash1',
            '2026-01-01 00:00:00',
            '2027-01-01 00:00:00',
            null,
            ''
        );

        $repo->create($record);
        $repo->revoke('tid1');

        $found = $repo->findByTokenId('tid1');
        self::assertNotNull($found);
        self::assertTrue($found->isRevoked());
    }

    public function testRevokeAllForUser(): void
    {
        $repo = new InMemoryTokenRepository();

        $repo->create(new TokenRecord('t1', 'user1', 'h1', '2026-01-01 00:00:00', '2027-01-01 00:00:00', null, ''));
        $repo->create(new TokenRecord('t2', 'user1', 'h2', '2026-01-01 00:00:00', '2027-01-01 00:00:00', null, ''));
        $repo->create(new TokenRecord('t3', 'user2', 'h3', '2026-01-01 00:00:00', '2027-01-01 00:00:00', null, ''));

        $repo->revokeAllForUser('user1');

        $found1 = $repo->findByTokenId('t1');
        $found2 = $repo->findByTokenId('t2');
        $found3 = $repo->findByTokenId('t3');

        self::assertNotNull($found1);
        self::assertTrue($found1->isRevoked());

        self::assertNotNull($found2);
        self::assertTrue($found2->isRevoked());

        self::assertNotNull($found3);
        self::assertFalse($found3->isRevoked());
    }
}

