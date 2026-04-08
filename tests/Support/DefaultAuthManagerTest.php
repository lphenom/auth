<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Support;

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;
use LPhenom\Auth\Contracts\PasswordHashUpdaterInterface;
use LPhenom\Auth\Contracts\UserProviderInterface;
use LPhenom\Auth\Hashing\CryptPasswordHasher;
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
        $hasher = new CryptPasswordHasher(100);
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
        $hasher = new CryptPasswordHasher(100);
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

    // -------------------------------------------------------------------------
    // Auto-rehash tests (CryptPasswordHasher iteration count upgrade)
    // -------------------------------------------------------------------------

    public function testRehashTriggeredWhenIterationCountChanged(): void
    {
        // Hash stored with old iteration count (100)
        $oldHasher = new CryptPasswordHasher(100);
        $oldHash   = $oldHasher->hash('migratepass');

        $provider = new StubUserProviderWithRehash();
        $provider->addUser(new StubUser('99', 'migrate@example.com', $oldHash, [], true));

        // Active hasher uses 200 iterations — needsRehash() returns true for old hash
        $newHasher = new CryptPasswordHasher(200);
        $manager = new DefaultAuthManager(
            $provider,
            $newHasher,
            new OpaqueTokenEncoder(),
            new InMemoryTokenRepository(),
            null,
            null,
            3600,
            5,
            60
        );

        $user = $manager->attempt('migrate@example.com', 'migratepass');

        self::assertNotNull($user, 'Login must succeed despite iteration count change');
        self::assertNotNull(
            $provider->getUpdatedHash('99'),
            'updateAuthPasswordHash() must be called when needsRehash() returns true'
        );

        $newHash = $provider->getUpdatedHash('99');
        self::assertIsString($newHash);
        self::assertStringStartsWith(
            '$lphenom$sha256$200$',
            $newHash,
            'Rehashed password must use the new iteration count'
        );
    }

    public function testRehashNotTriggeredForCurrentLphenomHash(): void
    {
        $hasher = new CryptPasswordHasher(200);
        $hash   = $hasher->hash('alreadycurrent');

        $provider = new StubUserProviderWithRehash();
        $provider->addUser(new StubUser('88', 'current@example.com', $hash, [], true));

        $manager = new DefaultAuthManager(
            $provider,
            $hasher, // same iteration count → needsRehash() === false
            new OpaqueTokenEncoder(),
            new InMemoryTokenRepository(),
            null,
            null,
            3600,
            5,
            60
        );

        $manager->attempt('current@example.com', 'alreadycurrent');

        self::assertNull(
            $provider->getUpdatedHash('88'),
            'updateAuthPasswordHash() must NOT be called when hash is already current'
        );
    }

    public function testRehashSkippedWhenProviderDoesNotImplementUpdater(): void
    {
        // Hash made with old iterations — needsRehash() would return true with new hasher
        $oldHasher = new CryptPasswordHasher(100);
        $oldHash   = $oldHasher->hash('norehash');

        $this->userProvider->addUser(
            new StubUser('77', 'norehash@example.com', $oldHash, [], true)
        );

        $newHasher = new CryptPasswordHasher(200);
        $manager = new DefaultAuthManager(
            $this->userProvider,   // StubUserProvider — no PasswordHashUpdaterInterface
            $newHasher,
            new OpaqueTokenEncoder(),
            new InMemoryTokenRepository(),
            null,
            null,
            3600,
            5,
            60
        );

        // Must not throw — silently skip the rehash
        $user = $manager->attempt('norehash@example.com', 'norehash');
        self::assertNotNull($user, 'Login must succeed even when provider does not support rehash');
    }

    public function testSameHasherWorksBothBuilds(): void
    {
        // CryptPasswordHasher is @lphenom-build shared,kphp.
        // A hash produced in one build must verify in the other — no migration ever needed.
        $hasher = new CryptPasswordHasher(100);
        $hash   = $hasher->hash('crossbuild');

        $provider = new StubUserProviderWithRehash();
        $provider->addUser(new StubUser('66', 'cross@example.com', $hash, [], true));

        $manager = new DefaultAuthManager(
            $provider,
            $hasher,
            new OpaqueTokenEncoder(),
            new InMemoryTokenRepository(),
            null,
            null,
            3600,
            5,
            60
        );

        $user = $manager->attempt('cross@example.com', 'crossbuild');
        self::assertNotNull($user, 'CryptPasswordHasher hash must verify in any build');
        self::assertNull(
            $provider->getUpdatedHash('66'),
            'No rehash needed when iteration count matches'
        );
    }
}

/**
 * Stub user provider that also implements PasswordHashUpdaterInterface.
 * Tracks hash updates so tests can assert they happened.
 */
final class StubUserProviderWithRehash implements UserProviderInterface, PasswordHashUpdaterInterface
{
    /** @var array<string, StubUser> keyed by ID */
    private array $byId = [];

    /** @var array<string, StubUser> keyed by login */
    private array $byLogin = [];

    /** @var array<string, string> userId → newHash, captured from updateAuthPasswordHash() */
    private array $updatedHashes = [];

    public function addUser(StubUser $user): void
    {
        $this->byId[$user->getAuthIdentifier()] = $user;
        $this->byLogin[$user->getLogin()]        = $user;
    }

    public function findById(string $id): ?AuthenticatedUserInterface
    {
        return $this->byId[$id] ?? null;
    }

    public function findByLogin(string $login): ?AuthenticatedUserInterface
    {
        return $this->byLogin[$login] ?? null;
    }

    public function updateAuthPasswordHash(string $userId, string $newHash): void
    {
        $this->updatedHashes[$userId] = $newHash;
    }

    public function getUpdatedHash(string $userId): ?string
    {
        return $this->updatedHashes[$userId] ?? null;
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
