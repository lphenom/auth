<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Support;

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;
use LPhenom\Auth\Contracts\UserProviderInterface;
use LPhenom\Auth\Hashing\BcryptPasswordHasher;
use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Auth\Support\DefaultAuthManager;
use LPhenom\Auth\Support\InMemoryTokenRepository;
use LPhenom\Auth\Support\MemoryThrottle;
use LPhenom\Auth\Tokens\OpaqueTokenEncoder;
use PHPUnit\Framework\TestCase;

final class DefaultAuthManagerTest extends TestCase
{
    private DefaultAuthManager $manager;
    private InMemoryTokenRepository $tokenRepo;
    private StubUserProvider $userProvider;

    protected function setUp(): void
    {
        AuthContextStorage::reset();

        $this->userProvider = new StubUserProvider();
        $hasher = new BcryptPasswordHasher(4);
        $encoder = new OpaqueTokenEncoder();
        $this->tokenRepo = new InMemoryTokenRepository();
        $throttle = new MemoryThrottle();

        // Add a test user
        $this->userProvider->addUser(new StubUser(
            '1',
            'user@example.com',
            $hasher->hash('password123'),
            ['admin'],
            true
        ));

        $this->manager = new DefaultAuthManager(
            $this->userProvider,
            $hasher,
            $encoder,
            $this->tokenRepo,
            $throttle,
            null,
            86400,
            5,
            60
        );
    }

    public function testAttemptSuccess(): void
    {
        $user = $this->manager->attempt('user@example.com', 'password123');
        self::assertNotNull($user);
        self::assertSame('1', $user->getAuthIdentifier());
    }

    public function testAttemptFailWrongPassword(): void
    {
        $user = $this->manager->attempt('user@example.com', 'wrong');
        self::assertNull($user);
    }

    public function testAttemptFailUnknownUser(): void
    {
        $user = $this->manager->attempt('nobody@example.com', 'password123');
        self::assertNull($user);
    }

    public function testIssueTokenAndAuthenticateBearer(): void
    {
        $user = $this->manager->attempt('user@example.com', 'password123');
        self::assertNotNull($user);

        $issued = $this->manager->issueToken($user);
        self::assertNotEmpty($issued->plainTextToken);

        $authUser = $this->manager->authenticateBearer('Bearer ' . $issued->plainTextToken);
        self::assertNotNull($authUser);
        self::assertSame('1', $authUser->getAuthIdentifier());

        // Auth context should be stored
        $ctx = AuthContextStorage::get();
        self::assertNotNull($ctx);
        self::assertSame('1', $ctx->user->getAuthIdentifier());
    }

    public function testAuthenticateBearerInvalidToken(): void
    {
        $user = $this->manager->authenticateBearer('Bearer invalid.token');
        self::assertNull($user);
    }

    public function testAuthenticateBearerNullHeader(): void
    {
        $user = $this->manager->authenticateBearer(null);
        self::assertNull($user);
    }

    public function testAuthenticateBearerWrongPrefix(): void
    {
        $user = $this->manager->authenticateBearer('Basic abc123');
        self::assertNull($user);
    }

    public function testLogoutToken(): void
    {
        $user = $this->manager->attempt('user@example.com', 'password123');
        self::assertNotNull($user);

        $issued = $this->manager->issueToken($user);
        self::assertNotEmpty($issued->plainTextToken);

        // Token should work before logout
        AuthContextStorage::reset();
        $authUser = $this->manager->authenticateBearer('Bearer ' . $issued->plainTextToken);
        self::assertNotNull($authUser);

        // Logout
        $this->manager->logoutToken($issued->plainTextToken);

        // Token should not work after logout
        AuthContextStorage::reset();
        $authUser = $this->manager->authenticateBearer('Bearer ' . $issued->plainTextToken);
        self::assertNull($authUser);
    }

    public function testThrottlePreventsLogin(): void
    {
        // Exhaust attempts (max 5)
        for ($i = 0; $i < 5; $i++) {
            $this->manager->attempt('user@example.com', 'wrong');
        }

        // Now even correct password should be blocked
        $user = $this->manager->attempt('user@example.com', 'password123');
        self::assertNull($user);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $hasher = new BcryptPasswordHasher(4);
        $this->userProvider->addUser(new StubUser(
            '2',
            'inactive@example.com',
            $hasher->hash('pass'),
            [],
            false
        ));

        $user = $this->manager->attempt('inactive@example.com', 'pass');
        self::assertNull($user);
    }
}

/**
 * Stub user for testing — implements AuthenticatedUserInterface.
 */
final class StubUser implements AuthenticatedUserInterface
{
    /** @var string */
    private string $id;

    /** @var string */
    private string $login;

    /** @var ?string */
    private ?string $passwordHash;

    /** @var string[] */
    private array $roles;

    /** @var bool */
    private bool $active;

    /**
     * @param string[] $roles
     */
    public function __construct(
        string $id,
        string $login,
        ?string $passwordHash,
        array $roles,
        bool $active
    ) {
        $this->id           = $id;
        $this->login        = $login;
        $this->passwordHash = $passwordHash;
        $this->roles        = $roles;
        $this->active       = $active;
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
        return $this->passwordHash;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getLogin(): string
    {
        return $this->login;
    }
}

/**
 * Stub user provider for testing.
 */
final class StubUserProvider implements UserProviderInterface
{
    /** @var array<string, StubUser> keyed by ID */
    private array $byId = [];

    /** @var array<string, StubUser> keyed by login */
    private array $byLogin = [];

    public function addUser(StubUser $user): void
    {
        $this->byId[$user->getAuthIdentifier()] = $user;
        $this->byLogin[$user->getLogin()] = $user;
    }

    public function findById(string $id): ?AuthenticatedUserInterface
    {
        return $this->byId[$id] ?? null;
    }

    public function findByLogin(string $login): ?AuthenticatedUserInterface
    {
        return $this->byLogin[$login] ?? null;
    }
}
