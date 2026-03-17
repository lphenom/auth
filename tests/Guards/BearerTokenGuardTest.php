<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Guards;

use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Tests\Middleware\StubAuthManager;
use LPhenom\Auth\Tests\Middleware\StubAuthUser;
use LPhenom\Http\Request;
use PHPUnit\Framework\TestCase;

final class BearerTokenGuardTest extends TestCase
{
    public function testAuthenticateWithValidHeader(): void
    {
        $user = new StubAuthUser('1', ['user'], true);
        $authManager = new StubAuthManager($user);
        $guard = new BearerTokenGuard($authManager);

        $request = new Request('GET', '/api', [], ['Authorization' => 'Bearer test.token'], [], '', [], '127.0.0.1');
        $result = $guard->authenticate($request);

        self::assertNotNull($result);
        self::assertSame('1', $result->getAuthIdentifier());
    }

    public function testAuthenticateWithNoHeader(): void
    {
        $authManager = new StubAuthManager(null);
        $guard = new BearerTokenGuard($authManager);

        $request = new Request('GET', '/api', [], [], [], '', [], '127.0.0.1');
        $result = $guard->authenticate($request);

        self::assertNull($result);
    }
}
