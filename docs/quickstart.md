# Быстрый старт

## Установка

```bash
composer require lphenom/auth
```

Если вы используете VCS-репозитории, добавьте в `composer.json`:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lphenom/auth"},
        {"type": "vcs", "url": "https://github.com/lphenom/core"},
        {"type": "vcs", "url": "https://github.com/lphenom/http"},
        {"type": "vcs", "url": "https://github.com/lphenom/cache"},
        {"type": "vcs", "url": "https://github.com/lphenom/log"},
        {"type": "vcs", "url": "https://github.com/lphenom/db"}
    ]
}
```

## 1. Реализуйте интерфейс пользователя

Ваша модель пользователя должна реализовать `AuthenticatedUserInterface`:

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;

final class User implements AuthenticatedUserInterface
{
    /** @var string */
    private string $id;

    /** @var string */
    private string $email;

    /** @var string */
    private string $passwordHash;

    /** @var string[] */
    private array $roles;

    /** @var bool */
    private bool $active;

    /**
     * @param string[] $roles
     */
    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        array $roles,
        bool $active
    ) {
        $this->id           = $id;
        $this->email        = $email;
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
}
```

## 2. Реализуйте UserProvider

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Contracts\AuthenticatedUserInterface;
use LPhenom\Auth\Contracts\UserProviderInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Param\Param;

final class DbUserProvider implements UserProviderInterface
{
    /** @var ConnectionInterface */
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function findById(string $id): ?AuthenticatedUserInterface
    {
        /** @var array<string, Param> $params */
        $params = ['id' => new Param($id, 2)];
        $result = $this->db->query('SELECT * FROM users WHERE id = :id', $params);
        $row = $result->fetchOne();
        if ($row === null) {
            return null;
        }
        return $this->hydrateUser($row);
    }

    public function findByLogin(string $login): ?AuthenticatedUserInterface
    {
        /** @var array<string, Param> $params */
        $params = ['email' => new Param($login, 2)];
        $result = $this->db->query('SELECT * FROM users WHERE email = :email', $params);
        $row = $result->fetchOne();
        if ($row === null) {
            return null;
        }
        return $this->hydrateUser($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateUser(array $row): User
    {
        $id = isset($row['id']) && is_string($row['id']) ? $row['id'] : '';
        $email = isset($row['email']) && is_string($row['email']) ? $row['email'] : '';
        $hash = isset($row['password_hash']) && is_string($row['password_hash']) ? $row['password_hash'] : '';
        $active = isset($row['is_active']) && $row['is_active'] === '1';

        // Роли можно загружать из отдельной таблицы или хранить как JSON
        /** @var string[] $roles */
        $roles = ['user'];

        return new User($id, $email, $hash, $roles, $active);
    }
}
```

## 3. Настройте AuthManager

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Hashing\BcryptPasswordHasher;
use LPhenom\Auth\Support\DefaultAuthManager;
use LPhenom\Auth\Support\DbTokenRepository;
use LPhenom\Auth\Support\LogAuditListener;
use LPhenom\Auth\Support\CacheThrottle;
use LPhenom\Auth\Tokens\OpaqueTokenEncoder;

// Зависимости
$hasher     = new BcryptPasswordHasher(10);
$encoder    = new OpaqueTokenEncoder();
$tokenRepo  = new DbTokenRepository($dbConnection);
$throttle   = new CacheThrottle($cache);
$audit      = new LogAuditListener($logger);

// Создаём менеджер
$authManager = new DefaultAuthManager(
    $userProvider,   // ваш DbUserProvider
    $hasher,
    $encoder,
    $tokenRepo,
    $throttle,       // null если не нужен
    $audit,          // null если не нужен
    86400,           // TTL токена в секундах (24 часа)
    5,               // Макс. попыток логина
    60               // Время блокировки в секундах
);
```

## 4. Аутентификация

### Логин по credentials

```php
$user = $authManager->attempt('user@example.com', 'password123');
if ($user === null) {
    // Неверный логин или пароль
    echo 'Login failed';
} else {
    $issued = $authManager->issueToken($user);
    echo 'Token: ' . $issued->plainTextToken;
    echo 'Expires: ' . $issued->expiresAt;
}
```

### Проверка bearer токена

```php
$user = $authManager->authenticateBearer($request->getHeader('Authorization'));
if ($user === null) {
    // Токен невалиден
}
```

### Logout (отзыв токена)

```php
$authManager->logoutToken($plainBearerToken);
```

## 5. Middleware

См. [docs/http-integration.md](./http-integration.md) для подробной интеграции с HTTP pipeline.

## Следующие шаги

- [Bearer токены](./bearer-tokens.md) — как работает система токенов
- [HTTP интеграция](./http-integration.md) — middleware и guards
- [SMS / Email коды](./sms-email-auth.md) — авторизация по одноразовым кодам
- [Безопасность](./security.md) — рекомендации по безопасности

