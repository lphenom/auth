<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Middleware;

use LPhenom\Auth\DTO\AuthContext;
use LPhenom\Auth\Middleware\RequireRoleMiddleware;
use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use PHPUnit\Framework\TestCase;

final class RequireRoleMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        AuthContextStorage::reset();
    }

    public function testReturns401WhenNoContext(): void
    {
        $middleware = new RequireRoleMiddleware(['admin']);

        $request = new Request('GET', '/admin', [], [], [], '', [], '127.0.0.1');
        $handler = new StubHandler();
        $next = new Next([], $handler);

        $response = $middleware->process($request, $next);
        self::assertSame(401, $response->getStatus());
    }

    public function testReturns403WhenRoleMissing(): void
    {
        $user = new StubAuthUser('1', ['user'], true);
        AuthContextStorage::set(new AuthContext($user, 'tok-1', []));

        $middleware = new RequireRoleMiddleware(['admin']);

        $request = new Request('GET', '/admin', [], [], [], '', [], '127.0.0.1');
        $handler = new StubHandler();
        $next = new Next([], $handler);

        $response = $middleware->process($request, $next);
        self::assertSame(403, $response->getStatus());
    }

    public function testPassesWhenRolePresent(): void
    {
        $user = new StubAuthUser('1', ['admin', 'user'], true);
        AuthContextStorage::set(new AuthContext($user, 'tok-1', []));

        $middleware = new RequireRoleMiddleware(['admin']);

        $request = new Request('GET', '/admin', [], [], [], '', [], '127.0.0.1');
        $handler = new StubHandler();
        $next = new Next([], $handler);

        $response = $middleware->process($request, $next);
        self::assertSame(200, $response->getStatus());
    }
}

