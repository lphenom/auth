<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Support;

use LPhenom\Auth\Support\MemoryThrottle;
use PHPUnit\Framework\TestCase;

final class MemoryThrottleTest extends TestCase
{
    public function testNoAttemptsInitially(): void
    {
        $throttle = new MemoryThrottle();
        self::assertFalse($throttle->tooManyAttempts('key1', 3));
    }

    public function testHitIncrementsAttempts(): void
    {
        $throttle = new MemoryThrottle();
        $throttle->hit('key1', 60);
        $throttle->hit('key1', 60);
        self::assertFalse($throttle->tooManyAttempts('key1', 3));

        $throttle->hit('key1', 60);
        self::assertTrue($throttle->tooManyAttempts('key1', 3));
    }

    public function testResetClearsAttempts(): void
    {
        $throttle = new MemoryThrottle();
        $throttle->hit('key1', 60);
        $throttle->hit('key1', 60);
        $throttle->hit('key1', 60);
        self::assertTrue($throttle->tooManyAttempts('key1', 3));

        $throttle->reset('key1');
        self::assertFalse($throttle->tooManyAttempts('key1', 3));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $throttle = new MemoryThrottle();
        $throttle->hit('key1', 60);
        $throttle->hit('key1', 60);
        $throttle->hit('key1', 60);

        self::assertTrue($throttle->tooManyAttempts('key1', 3));
        self::assertFalse($throttle->tooManyAttempts('key2', 3));
    }
}

