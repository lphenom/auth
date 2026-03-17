<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Middleware;

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;
use LPhenom\Auth\Contracts\AuthManagerInterface;
use LPhenom\Auth\DTO\IssuedToken;
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Middleware\RequireAuthMiddleware;
use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use PHPUnit\Framework\TestCase;

final class RequireAuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        AuthContextStorage::reset();
    }

    public function testReturns401WhenNotAuthenticated(): void
    {
        $authManager = new StubAuthManager(null);
        $guard = new BearerTokenGuard($authManager);
        $middleware = new RequireAuthMiddleware($guard);

        $request = new Request('GET', '/api/profile', [], [], [], '', [], '127.0.0.1');
        $handler = new StubHandler();
        $next = new Next([], $handler);

        $response = $middleware->process($request, $next);
        self::assertSame(401, $response->getStatus());
    }

    public function testPassesThroughWhenAuthenticated(): void
    {
        $user = new StubAuthUser('1', ['user'], true);
        $authManager = new StubAuthManager($user);
        $guard = new BearerTokenGuard($authManager);
        $middleware = new RequireAuthMiddleware($guard);

        $request = new Request('GET', '/api/profile', [], ['Authorization' => 'Bearer test.token'], [], '', [], '127.0.0.1');
        $handler = new StubHandler();
        $next = new Next([], $handler);

        $response = $middleware->process($request, $next);
        self::assertSame(200, $response->getStatus());
    }
}

/**
 * @internal
 */
final class StubAuthManager implements AuthManagerInterface
{
    /** @var ?AuthenticatedUserInterface */
    private ?AuthenticatedUserInterface $user;

    public function __construct(?AuthenticatedUserInterface $user)
    {
        $this->user = $user;
    }

    public function attempt(string $login, string $password): ?AuthenticatedUserInterface
    {
        return $this->user;
    }

    public function issueToken(AuthenticatedUserInterface $user, string $metaJson = ''): IssuedToken
    {
        return new IssuedToken('test.token', 'test', '2027-01-01 00:00:00');
    }

    public function authenticateBearer(?string $authorizationHeader): ?AuthenticatedUserInterface
    {
        return $this->user;
    }

    public function logoutToken(string $plainBearerToken): void
    {
    }
}

/**
 * @internal
 */
final class StubAuthUser implements AuthenticatedUserInterface
{
    /** @var string */
    private string $id;

    /** @var string[] */
    private array $roles;

    /** @var bool */
    private bool $active;

    /**
     * @param string[] $roles
     */
    public function __construct(string $id, array $roles, bool $active)
    {
        $this->id     = $id;
        $this->roles  = $roles;
        $this->active = $active;
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthRoles(): array
    {
        return $this->roles;
    }

    public function getAuthPasswordHash(): ?string
    {
        return null;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}

/**
 * @internal
 */
final class StubHandler implements HandlerInterface
{
    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }
}
